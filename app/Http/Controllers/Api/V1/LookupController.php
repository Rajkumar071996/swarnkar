<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ScoreSnapshotResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\ConsentService;
use App\Services\CreditExposure;
use App\Services\CustomerDirectory;
use App\Services\Scoring\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mirrors the web credit-check flow for the Flutter client. The consent gate is
 * enforced here too: an API caller cannot reach a score without one.
 */
class LookupController extends Controller
{
    public function __construct(
        private readonly CustomerDirectory $directory,
        private readonly ConsentService $consent,
        private readonly ScoreService $scores,
        private readonly CreditExposure $exposure,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        $results = $this->directory->search($data['q']);
        AuditLog::record('api.lookup.searched', null, ['matches' => $results->count()]);

        return response()->json(['data' => CustomerResource::collection($results)]);
    }

    public function requestConsent(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewScore', $customer);

        $consent = $this->consent->issue($customer, $request->user());

        return response()->json([
            'consent_request_id' => $consent->id,
            'expires_at' => $consent->otp_expires_at->toIso8601String(),
            'delivery' => $this->consent->channelDescription(),
        ], 201);
    }

    public function verifyConsent(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewScore', $customer);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:8'],
        ]);

        $pending = $this->consent->pendingRequest($customer, $request->user()->store_id);

        if (! $pending) {
            return response()->json([
                'message' => 'That consent request has expired. Request a new code.',
            ], 410);
        }

        $outcome = $this->consent->verify($pending, $data['code']);

        return response()->json([
            'granted' => $outcome['ok'],
            'message' => $outcome['message'],
            'grant_expires_at' => $outcome['consent']->grant_expires_at?->toIso8601String(),
        ], $outcome['ok'] ? 200 : 422);
    }

    public function score(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewScore', $customer);

        $grant = $this->consent->activeGrant($customer, $request->user()->store_id);

        if (! $grant) {
            return response()->json([
                'message' => 'Customer consent is required before a score can be released.',
                'consent_required' => true,
            ], 403);
        }

        $snapshot = $this->scores->current($customer);

        AuditLog::record('api.score.viewed', $customer, [
            'consent_request_id' => $grant->id,
            'score' => $snapshot->score,
        ]);

        return response()->json([
            'customer' => new CustomerResource($customer),
            'score' => new ScoreSnapshotResource($snapshot),
            // Shipped alongside the score because the decision at the counter
            // needs both: a good score still means no if they are already in
            // deep somewhere else.
            'exposure' => $this->exposure->for($customer, $request->user()->store_id)->toArray(),
            'consent_expires_at' => $grant->grant_expires_at->toIso8601String(),
        ]);
    }
}
