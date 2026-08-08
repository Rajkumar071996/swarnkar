<?php

namespace App\Http\Controllers\Girvi;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoldLoan;
use App\Services\Girvi\GirviLedger;
use App\Services\Girvi\MetalRates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GirviLoanController extends Controller
{
    public function __construct(
        private readonly GirviLedger $ledger,
        private readonly MetalRates $rates,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GoldLoan::class);

        $filter = $request->query('filter', 'unreleased');

        $loans = GoldLoan::query()
            ->with('customer')
            ->when($filter === 'unreleased', fn (Builder $q) => $q->unreleased())
            ->when($filter === 'released', fn (Builder $q) => $q->released())
            ->when($filter === 'overdue', fn (Builder $q) => $q->overdue())
            ->latest('disbursed_on')
            ->paginate(20)
            ->withQueryString();

        return view('girvi.loans.index', [
            'loans' => $loans,
            'filter' => $filter,
            'totals' => [
                'money_out' => (float) GoldLoan::query()->unreleased()
                    ->selectRaw('COALESCE(SUM(principal_amount - principal_repaid), 0) AS total')
                    ->value('total'),
                'fine_weight' => (float) GoldLoan::query()->unreleased()
                    ->selectRaw('COALESCE(SUM(fine_weight_grams), 0) AS total')
                    ->value('total'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GoldLoan::class);

        return view('girvi.loans.create', [
            'customers' => $this->storeCustomers($request),
            'selectedCustomer' => $request->integer('customer') ?: null,
            'rates' => $this->rates->current($request->user()->store_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', GoldLoan::class);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_no' => ['nullable', 'string', 'max:64'],
            'packet_no' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'disbursed_on' => ['required', 'date', 'before_or_equal:today'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'loan_reason' => ['nullable', 'string', 'max:64'],
            'loan_type' => ['nullable', 'string', 'max:64'],
            'estimate_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:200'],
            'principal_amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'refer_by' => ['nullable', 'string', 'max:128'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.metal_type' => ['required', Rule::in(array_keys(config('girvi.metal_types')))],
            'items.*.item_type' => ['required', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.gross_weight_grams' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'items.*.less_weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.weight_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'items.*.rate_per_gram' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $loan = $this->ledger->deposit($data, $data['items'], $request->user());

        return redirect()
            ->route('girvi.loans.show', $loan)
            ->with('success', 'Girvi '.$loan->receipt_no.' recorded for '.money($loan->principal_amount).'.');
    }

    public function show(GoldLoan $goldLoan): View
    {
        $this->authorize('view', $goldLoan);

        $goldLoan->load(['customer', 'items', 'payments.recordedBy', 'createdBy']);

        return view('girvi.loans.show', ['loan' => $goldLoan]);
    }

    public function receipt(GoldLoan $goldLoan): View
    {
        $this->authorize('view', $goldLoan);

        $goldLoan->load(['customer', 'items']);

        return view('girvi.loans.receipt', ['loan' => $goldLoan]);
    }

    public function collectInterest(Request $request, GoldLoan $goldLoan): RedirectResponse
    {
        $this->authorize('collect', $goldLoan);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:cash,upi,card,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        $this->ledger->collectInterest(
            $goldLoan,
            (float) $data['amount'],
            Carbon::parse($data['paid_on']),
            $data['method'],
            $data['reference'] ?? null,
            $request->user(),
        );

        return back()->with('success', money($data['amount']).' interest collected.');
    }

    /**
     * The counter looks a customer up by ledger number first, so it is pulled
     * off the store pivot alongside the name.
     *
     * @return Collection<int, Customer>
     */
    private function storeCustomers(Request $request): Collection
    {
        $storeId = $request->user()->store_id;

        return Customer::query()
            ->select(['id', 'full_name', 'mobile', 'city'])
            ->addSelect(['ledger_no' => DB::table('store_customer')
                ->select('ledger_no')
                ->whereColumn('customer_id', 'customers.id')
                ->where('store_id', $storeId)
                ->limit(1)])
            ->whereHas('stores', fn (Builder $q) => $q->whereKey($storeId))
            ->orderBy('full_name')
            ->get();
    }
}
