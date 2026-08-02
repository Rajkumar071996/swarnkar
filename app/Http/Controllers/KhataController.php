<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Udhaar;
use App\Models\UdhaarPayment;
use App\Services\ConsentService;
use App\Services\CreditExposure;
use App\Services\UdhaarLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The udhar khata: one running account per customer rather than a list of
 * loose bills. A shopkeeper thinks in terms of "what does Rajesh owe me", not
 * "what is the status of invoice 4471".
 */
class KhataController extends Controller
{
    public function __construct(
        private readonly CreditExposure $exposure,
        private readonly ConsentService $consent,
        private readonly UdhaarLedger $ledger,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Udhaar::class);

        $filter = $request->query('filter', 'outstanding');
        $storeId = $request->user()->store_id;

        $accounts = Customer::query()
            ->whereHas('stores', fn (Builder $q) => $q->whereKey($storeId))
            ->whereHas('udhaars')
            ->withCount('udhaars as entry_count')
            ->withCount(['udhaars as overdue_count' => fn (Builder $q) => $q->overdue()])
            ->withSum('udhaars as extended_total', 'principal_amount')
            ->withSum('udhaars as paid_total', 'amount_paid')
            ->withSum(
                ['udhaars as outstanding_total' => fn (Builder $q) => $q->outstanding()],
                DB::raw('principal_amount - amount_paid')
            )
            ->withMin(['udhaars as oldest_overdue_on' => fn (Builder $q) => $q->overdue()], 'due_on')
            ->when($filter === 'outstanding', fn (Builder $q) => $q->whereHas('udhaars', fn ($u) => $u->outstanding()))
            ->when($filter === 'overdue', fn (Builder $q) => $q->whereHas('udhaars', fn ($u) => $u->overdue()))
            ->when($filter === 'settled', fn (Builder $q) => $q->whereDoesntHave('udhaars', fn ($u) => $u->outstanding()))
            ->with('latestScore')
            ->orderByDesc('outstanding_total')
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('khata.index', [
            'accounts' => $accounts,
            'filter' => $filter,
            'totals' => [
                'outstanding' => (float) Udhaar::query()->outstanding()
                    ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
                'overdue' => (float) Udhaar::query()->overdue()
                    ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
                'accounts' => Udhaar::query()->outstanding()->distinct()->count('customer_id'),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        $storeId = $request->user()->store_id;

        $entries = Udhaar::query()
            ->where('customer_id', $customer->id)
            ->with('createdBy')
            ->latest('issued_on')
            ->get();

        $payments = UdhaarPayment::query()
            ->whereIn('udhaar_id', $entries->pluck('id'))
            ->with(['udhaar', 'recordedBy'])
            ->latest('paid_on')
            ->get();

        // Cross-store figures stay behind the consent gate; the shop's own
        // book is theirs to read whenever they like.
        $grant = $this->consent->activeGrant($customer, $storeId);

        return view('khata.show', [
            'customer' => $customer->load('latestScore'),
            'entries' => $entries,
            'payments' => $payments,
            'summary' => $this->summarise($entries),
            'exposure' => $grant ? $this->exposure->for($customer, $storeId) : null,
            'isLinked' => $customer->stores()->whereKey($storeId)->exists(),
        ]);
    }

    public function receiveForm(Request $request, ?Customer $customer = null): View
    {
        $this->authorize('viewAny', Udhaar::class);

        $storeId = $request->user()->store_id;
        $selected = $customer
            ?? ($request->integer('customer')
                ? Customer::query()->find($request->integer('customer'))
                : null);

        if ($selected) {
            $this->authorize('view', $selected);
        }

        $openEntries = $selected
            ? Udhaar::query()
                ->where('customer_id', $selected->id)
                ->outstanding()
                ->orderBy('due_on')
                ->get()
            : collect();

        return view('khata.receive', [
            'customer' => $selected,
            'customers' => Customer::query()
                ->whereHas('stores', fn (Builder $q) => $q->whereKey($storeId))
                ->whereHas('udhaars', fn (Builder $q) => $q->outstanding())
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'mobile']),
            'openEntries' => $openEntries,
            'outstanding' => round($openEntries->sum(fn (Udhaar $u) => $u->outstandingAmount()), 2),
        ]);
    }

    public function receive(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('view', $customer);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:cash,upi,card,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:128'],
            'udhaar_id' => [
                'nullable',
                'integer',
                'exists:udhaars,id',
            ],
        ]);

        $payments = $this->ledger->receive(
            $customer,
            (float) $data['amount'],
            Carbon::parse($data['paid_on']),
            $data['method'],
            $data['reference'] ?? null,
            $request->user(),
            isset($data['udhaar_id']) ? (int) $data['udhaar_id'] : null,
        );

        $message = money($data['amount']).' received'
            .($payments->count() > 1 ? ' across '.$payments->count().' credit entries' : '')
            .'.';

        return redirect()
            ->route('khata.show', $customer)
            ->with('success', $message);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Udhaar>  $entries
     * @return array<string, mixed>
     */
    private function summarise($entries): array
    {
        $outstandingEntries = $entries->filter(fn (Udhaar $u) => $u->status->isOutstanding());

        return [
            'entries' => $entries->count(),
            'extended' => round((float) $entries->sum('principal_amount'), 2),
            'paid' => round((float) $entries->sum('amount_paid'), 2),
            'outstanding' => round($outstandingEntries->sum(fn (Udhaar $u) => $u->outstandingAmount()), 2),
            'open_entries' => $outstandingEntries->count(),
            'overdue' => round(
                $outstandingEntries->filter(fn (Udhaar $u) => $u->daysOverdue() > 0)
                    ->sum(fn (Udhaar $u) => $u->outstandingAmount()),
                2
            ),
            'oldest_overdue_days' => (int) $outstandingEntries->max(fn (Udhaar $u) => $u->daysOverdue()),
            'written_off' => round(
                $entries->filter(fn (Udhaar $u) => $u->status->isWrittenOff())
                    ->sum(fn (Udhaar $u) => $u->outstandingAmount()),
                2
            ),
        ];
    }
}
