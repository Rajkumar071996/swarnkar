<?php

namespace App\Http\Controllers\Girvi;

use App\Http\Controllers\Controller;
use App\Models\GoldLoan;
use App\Services\Girvi\GirviLedger;
use App\Services\Girvi\InterestCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class GirviReleaseController extends Controller
{
    public function __construct(
        private readonly GirviLedger $ledger,
        private readonly InterestCalculator $interest,
    ) {}

    public function create(Request $request): View
    {
        $this->authorize('viewAny', GoldLoan::class);

        $term = trim((string) $request->query('q', ''));
        $selected = null;
        $summary = null;

        $matches = GoldLoan::query()
            ->unreleased()
            ->with('customer')
            ->when($term !== '', fn (Builder $q) => $q->where(function (Builder $inner) use ($term) {
                $inner->where('receipt_no', 'like', '%'.$term.'%')
                    ->orWhere('invoice_no', 'like', '%'.$term.'%')
                    ->orWhere('barcode', 'like', '%'.$term.'%')
                    ->orWhere('loan_no', 'like', '%'.$term.'%')
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('full_name', 'like', '%'.$term.'%'));
            }))
            ->orderBy('due_on')
            ->limit(25)
            ->get();

        if ($request->integer('loan')) {
            $selected = GoldLoan::query()->unreleased()->with(['customer', 'items'])
                ->find($request->integer('loan'));

            if ($selected) {
                $this->authorize('view', $selected);
                $summary = $this->interest->releaseSummary($selected, Carbon::today());
            }
        }

        return view('girvi.release.create', [
            'term' => $term,
            'matches' => $matches,
            'loan' => $selected,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, GoldLoan $goldLoan): RedirectResponse
    {
        $this->authorize('release', $goldLoan);

        $data = $request->validate([
            'released_on' => ['required', 'date', 'before_or_equal:today'],
            'extra_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'extra_interest' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notice_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'narration' => ['nullable', 'string', 'max:1000'],
        ]);

        $summary = $this->ledger->release(
            $goldLoan,
            Carbon::parse($data['released_on']),
            [
                'extra_amount' => (float) ($data['extra_amount'] ?? 0),
                'extra_interest' => (float) ($data['extra_interest'] ?? 0),
                'notice_charge' => (float) ($data['notice_charge'] ?? 0),
                'discount' => (float) ($data['discount'] ?? 0),
            ],
            $request->user(),
        );

        return redirect()
            ->route('girvi.release.receipt', $goldLoan)
            ->with('success', 'Pledge released. '.money($summary['total']).' collected on '.$summary['receipt_no'].'.');
    }

    public function receipt(GoldLoan $goldLoan): View
    {
        $this->authorize('view', $goldLoan);

        $goldLoan->load(['customer', 'items', 'payments', 'store']);

        return view('girvi.release.receipt', [
            'loan' => $goldLoan,
            'settlement' => $goldLoan->payments->firstWhere('type', 'principal'),
        ]);
    }
}
