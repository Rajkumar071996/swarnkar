<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Udhaar;
use App\Services\CustomerDirectory;
use App\Services\CustomerSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerDirectory $directory,
        private readonly CustomerSignature $signatures,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $term = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            // Only this store's own customers; strangers are reached via lookup.
            ->whereHas('stores', fn ($q) => $q->whereKey($request->user()->store_id))
            ->when($term !== '', fn ($q) => $q->matchingIdentifier($term))
            ->with('latestScore')
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'term'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validated();
        $aadhaar = $data['aadhaar'] ?? null;
        $localReference = $data['local_reference'] ?? null;
        $ledgerNo = $data['ledger_no'] ?? null;
        $signature = $data['signature'] ?? null;
        unset($data['aadhaar'], $data['local_reference'], $data['ledger_no'], $data['signature']);

        $existing = $this->directory->findByIdentifier($data['mobile']);

        $customer = $this->directory->resolve([
            ...$data,
            'created_by_store_id' => $existing?->created_by_store_id ?? $request->user()->store_id,
        ], $aadhaar);

        $this->directory->linkToStore($customer, $request->user()->store_id, array_filter([
            'local_reference' => $localReference,
            'ledger_no' => $ledgerNo,
        ]));

        $this->signatures->store($customer, $signature);

        AuditLog::record('customer.saved', $customer, ['matched_existing' => (bool) $existing]);

        return redirect()->route('customers.show', $customer)->with(
            'success',
            $existing
                ? 'This person already had a profile on the GoldScore network. It is now linked to your store.'
                : $customer->full_name.' was added.'
        );
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load('latestScore');

        $udhaars = Udhaar::where('customer_id', $customer->id)
            ->latest('issued_on')
            ->get();

        return view('customers.show', [
            'customer' => $customer,
            'udhaars' => $udhaars,
            'isLinked' => $this->directory->isKnownToStore($customer, $request->user()->store_id),
        ]);
    }

    public function edit(Request $request, Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'ledgerNo' => $customer->ledgerNoFor($request->user()->store_id),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validated();
        $aadhaar = $data['aadhaar'] ?? null;
        $ledgerNo = $data['ledger_no'] ?? null;
        $signature = $data['signature'] ?? null;
        unset($data['aadhaar'], $data['local_reference'], $data['ledger_no'], $data['signature']);

        $this->directory->linkToStore($customer, $request->user()->store_id, [
            'ledger_no' => $ledgerNo,
        ]);

        $customer->fill($data);

        if (filled($aadhaar)) {
            $customer->setAadhaar($aadhaar);
        }

        $customer->save();
        $this->signatures->store($customer, $signature);
        AuditLog::record('customer.updated', $customer);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer details updated.');
    }
}
