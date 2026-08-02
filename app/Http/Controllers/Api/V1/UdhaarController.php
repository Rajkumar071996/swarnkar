<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UdhaarResource;
use App\Models\Udhaar;
use App\Services\UdhaarLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class UdhaarController extends Controller
{
    public function __construct(private readonly UdhaarLedger $ledger) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Udhaar::class);

        $udhaars = Udhaar::query()
            ->with('customer')
            ->when($request->boolean('outstanding'), fn ($q) => $q->outstanding())
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->latest('due_on')
            ->paginate($request->integer('per_page', 25));

        return UdhaarResource::collection($udhaars);
    }

    public function store(Request $request): JsonResponse
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

        return (new UdhaarResource($udhaar->load('customer')))->response()->setStatusCode(201);
    }

    public function show(Udhaar $udhaar): UdhaarResource
    {
        $this->authorize('view', $udhaar);

        return new UdhaarResource($udhaar->load('customer'));
    }

    public function recordPayment(Request $request, Udhaar $udhaar): UdhaarResource
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

        return new UdhaarResource($udhaar->refresh()->load('customer'));
    }
}
