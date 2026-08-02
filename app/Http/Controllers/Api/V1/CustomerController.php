<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\CustomerDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerDirectory $directory) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->whereHas('stores', fn ($q) => $q->whereKey($request->user()->store_id))
            ->when($request->filled('q'), fn ($q) => $q->matchingIdentifier($request->string('q')->toString()))
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 25));

        return CustomerResource::collection($customers);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validated();
        $aadhaar = $data['aadhaar'] ?? null;
        $localReference = $data['local_reference'] ?? null;
        unset($data['aadhaar'], $data['local_reference']);

        $existing = $this->directory->findByIdentifier($data['mobile']);

        $customer = $this->directory->resolve([
            ...$data,
            'created_by_store_id' => $existing?->created_by_store_id ?? $request->user()->store_id,
        ], $aadhaar);

        $this->directory->linkToStore($customer, $request->user()->store_id, [
            'local_reference' => $localReference,
        ]);

        AuditLog::record('api.customer.saved', $customer, ['matched_existing' => (bool) $existing]);

        return (new CustomerResource($customer))
            ->additional(['matched_existing_profile' => (bool) $existing])
            ->response()
            ->setStatusCode($existing ? 200 : 201);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }
}
