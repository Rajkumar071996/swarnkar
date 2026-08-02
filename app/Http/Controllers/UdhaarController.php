<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Udhaar;
use App\Services\UdhaarLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class UdhaarController extends Controller
{
    public function __construct(private readonly UdhaarLedger $ledger) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Udhaar::class);

        $filter = $request->query('filter', 'outstanding');

        $udhaars = Udhaar::query()
            ->with('customer')
            ->when($filter === 'outstanding', fn ($q) => $q->outstanding())
            ->when($filter === 'overdue', fn ($q) => $q->overdue())
            ->latest('due_on')
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'outstanding' => (float) Udhaar::query()->outstanding()
                ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
            'overdue' => (float) Udhaar::query()->overdue()
                ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
        ];

        return view('udhaars.index', compact('udhaars', 'filter', 'totals'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Udhaar::class);

        return view('udhaars.create', [
            'customers' => $this->storeCustomers($request),
            'selectedCustomer' => $request->integer('customer') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Udhaar::class);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_no' => ['nullable', 'string', 'max:64'],
            'item_description' => ['required', 'string', 'max:255'],
            'principal_amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'collateral_description' => ['nullable', 'string', 'max:255'],
            'collateral_weight_grams' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $udhaar = $this->ledger->issue($data, $request->user());

        return redirect()->route('udhaars.show', $udhaar)
            ->with('success', 'Udhaar of '.money($udhaar->principal_amount).' recorded.');
    }

    public function show(Udhaar $udhaar): View
    {
        $this->authorize('view', $udhaar);

        $udhaar->load(['customer', 'payments.recordedBy', 'createdBy']);

        return view('udhaars.show', compact('udhaar'));
    }

    public function recordPayment(Request $request, Udhaar $udhaar): RedirectResponse
    {
        $this->authorize('recordPayment', $udhaar);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:cash,upi,card,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        $this->ledger->recordPayment(
            $udhaar,
            (float) $data['amount'],
            Carbon::parse($data['paid_on']),
            $data['method'],
            $data['reference'] ?? null,
            $request->user(),
        );

        return back()->with('success', money($data['amount']).' recorded against this account.');
    }

    public function writeOff(Request $request, Udhaar $udhaar): RedirectResponse
    {
        $this->authorize('writeOff', $udhaar);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $this->ledger->writeOff($udhaar, $request->user(), $data['notes'] ?? null);

        return back()->with('warning', 'This account was written off and the customer score updated.');
    }

    private function storeCustomers(Request $request)
    {
        return Customer::query()
            ->whereHas('stores', fn ($q) => $q->whereKey($request->user()->store_id))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'mobile']);
    }
}
