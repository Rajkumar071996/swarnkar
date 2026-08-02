<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DefaultFlag;
use App\Models\Udhaar;
use App\Services\ConsentService;
use App\Services\CreditExposure;
use App\Services\CustomerDirectory;
use App\Services\Scoring\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The credit-check counter flow: find the customer, get their OTP consent, then
 * show the score. Nothing about the score is rendered before consent is live.
 */
class LookupController extends Controller
{
    public function __construct(
        private readonly CustomerDirectory $directory,
        private readonly ConsentService $consent,
        private readonly ScoreService $scores,
        private readonly CreditExposure $exposure,
    ) {}

    public function index(Request $request): View
    {
        return view('lookup.index', [
            'term' => trim((string) $request->query('q', '')),
            'results' => collect(),
            'channelHint' => $this->consent->channelDescription(),
        ]);
    }

    public function search(Request $request): View
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        $term = trim($data['q']);
        $results = $this->directory->search($term);

        AuditLog::record('lookup.searched', null, ['matches' => $results->count()]);

        return view('lookup.index', [
            'term' => $term,
            'results' => $results,
            'channelHint' => $this->consent->channelDescription(),
        ]);
    }

    public function requestConsent(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('viewScore', $customer);

        $this->consent->issue($customer, $request->user());

        return redirect()
            ->route('lookup.report', $customer)
            ->with('status', 'A consent code was sent to '.$customer->maskedMobile().'.');
    }

    public function verifyConsent(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('viewScore', $customer);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:8'],
        ]);

        $pending = $this->consent->pendingRequest($customer, $request->user()->store_id);

        if (! $pending) {
            return redirect()->route('lookup.report', $customer)
                ->with('error', 'That consent request has expired. Please send a new code.');
        }

        $outcome = $this->consent->verify($pending, $data['code']);

        return redirect()->route('lookup.report', $customer)->with(
            $outcome['ok'] ? 'success' : 'error',
            $outcome['message']
        );
    }

    public function report(Request $request, Customer $customer): View
    {
        $this->authorize('viewScore', $customer);

        $storeId = $request->user()->store_id;
        $grant = $this->consent->activeGrant($customer, $storeId);

        if (! $grant) {
            return view('lookup.consent', [
                'customer' => $customer,
                'pending' => $this->consent->pendingRequest($customer, $storeId),
                'channelHint' => $this->consent->channelDescription(),
            ]);
        }

        $snapshot = $this->scores->current($customer);

        AuditLog::record('score.viewed', $customer, [
            'consent_request_id' => $grant->id,
            'score' => $snapshot->score,
        ]);

        return view('lookup.report', [
            'customer' => $customer,
            'snapshot' => $snapshot,
            'grant' => $grant,
            'exposure' => $this->exposure->for($customer, $storeId),
            'activity' => $this->networkActivity($customer),
        ]);
    }

    /**
     * A consented view spans every store on the network, but the merchants
     * behind those rows are reduced to a city so a lookup cannot be used to map
     * a competitor's customer book.
     */
    private function networkActivity(Customer $customer): array
    {
        $ownStoreId = Auth::user()->store_id;

        $udhaars = Udhaar::query()->networkWide()
            ->where('customer_id', $customer->id)
            ->with('store')
            ->latest('issued_on')
            ->get();

        $flags = DefaultFlag::query()->networkWide()
            ->where('customer_id', $customer->id)
            ->verified()
            ->with('store')
            ->latest('occurred_on')
            ->get();

        return [
            'udhaars' => $udhaars->map(fn (Udhaar $u) => [
                'source' => $u->store_id === $ownStoreId ? 'Your store' : $u->store->anonymisedLabel(),
                'principal' => (float) $u->principal_amount,
                'outstanding' => $u->outstandingAmount(),
                'issued_on' => $u->issued_on,
                'due_on' => $u->due_on,
                'status' => $u->status,
            ]),
            'flags' => $flags->map(fn (DefaultFlag $f) => [
                'source' => $f->store_id === $ownStoreId ? 'Your store' : $f->store->anonymisedLabel(),
                'reason' => $f->reason,
                'occurred_on' => $f->occurred_on,
                'amount' => (float) $f->amount_involved,
            ]),
        ];
    }
}
