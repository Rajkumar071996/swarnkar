<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\UdhaarResource;
use App\Models\Customer;
use App\Models\Udhaar;
use App\Services\ConsentService;
use App\Services\CreditExposure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Account-level view of store credit for the Flutter client. Mirrors the web
 * khata screens, including the rule that cross-store figures need consent.
 */
class KhataController extends Controller
{
    public function __construct(
        private readonly CreditExposure $exposure,
        private readonly ConsentService $consent,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Udhaar::class);

        $accounts = Customer::query()
            ->whereHas('stores', fn (Builder $q) => $q->whereKey($request->user()->store_id))
            ->whereHas('udhaars')
            ->withCount('udhaars as entry_count')
            ->withCount(['udhaars as overdue_count' => fn (Builder $q) => $q->overdue()])
            ->withSum('udhaars as extended_total', 'principal_amount')
            ->withSum('udhaars as paid_total', 'amount_paid')
            ->withSum(
                ['udhaars as outstanding_total' => fn (Builder $q) => $q->outstanding()],
                DB::raw('principal_amount - amount_paid')
            )
            ->when($request->boolean('outstanding'), fn (Builder $q) => $q->whereHas('udhaars', fn ($u) => $u->outstanding()))
            ->orderByDesc('outstanding_total')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $accounts->getCollection()->map(fn (Customer $customer) => [
                'customer' => new CustomerResource($customer),
                'entries' => (int) $customer->entry_count,
                'overdue_entries' => (int) $customer->overdue_count,
                'credit_extended' => round((float) $customer->extended_total, 2),
                'paid' => round((float) $customer->paid_total, 2),
                'outstanding' => round((float) $customer->outstanding_total, 2),
            ]),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $entries = Udhaar::query()
            ->where('customer_id', $customer->id)
            ->with('customer')
            ->latest('issued_on')
            ->get();

        $outstanding = $entries->filter(fn (Udhaar $u) => $u->status->isOutstanding());

        return response()->json([
            'customer' => new CustomerResource($customer),
            'summary' => [
                'entries' => $entries->count(),
                'credit_extended' => round((float) $entries->sum('principal_amount'), 2),
                'paid' => round((float) $entries->sum('amount_paid'), 2),
                'outstanding' => round($outstanding->sum(fn (Udhaar $u) => $u->outstandingAmount()), 2),
                'oldest_overdue_days' => (int) $outstanding->max(fn (Udhaar $u) => $u->daysOverdue()),
            ],
            'entries' => UdhaarResource::collection($entries),
        ]);
    }

    /**
     * The cross-store position. Consent-gated for the same reason the score is:
     * it is the customer's data, not the network's.
     */
    public function exposure(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewScore', $customer);

        $storeId = $request->user()->store_id;

        if (! $this->consent->activeGrant($customer, $storeId)) {
            return response()->json([
                'message' => 'Customer consent is required before network exposure can be released.',
                'consent_required' => true,
            ], 403);
        }

        return response()->json([
            'customer' => new CustomerResource($customer),
            'exposure' => $this->exposure->for($customer, $storeId)->toArray(),
        ]);
    }
}
