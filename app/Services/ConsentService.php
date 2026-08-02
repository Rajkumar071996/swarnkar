<?php

namespace App\Services;

use App\Enums\ConsentStatus;
use App\Models\AuditLog;
use App\Models\ConsentRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\Otp\OtpChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

/**
 * Enforces the DPDP requirement that a jeweller cannot see a score until the
 * customer authorises that specific store to pull it. Grants are short-lived
 * and per store, so one authorisation never unlocks the report elsewhere.
 */
class ConsentService
{
    public function __construct(private readonly OtpChannel $channel) {}

    public function issue(Customer $customer, User $user, string $purpose = 'credit_check'): ConsentRequest
    {
        // Supersede any half-finished request so a stale OTP cannot be replayed.
        ConsentRequest::withoutGlobalScopes()
            ->where('store_id', $user->store_id)
            ->where('customer_id', $customer->id)
            ->where('status', ConsentStatus::Pending->value)
            ->update(['status' => ConsentStatus::Expired->value]);

        $code = $this->channel->generate();

        $consent = ConsentRequest::create([
            'store_id' => $user->store_id,
            'customer_id' => $customer->id,
            'requested_by_user_id' => $user->id,
            'purpose' => $purpose,
            'status' => ConsentStatus::Pending,
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => Carbon::now()->addMinutes((int) config('goldscore.consent.otp_ttl_minutes')),
            'ip_address' => Request::ip(),
        ]);

        $this->channel->send($customer, $code, $consent);

        AuditLog::record('consent.requested', $consent, ['customer_id' => $customer->id]);

        return $consent;
    }

    /**
     * @return array{ok: bool, message: string, consent: ConsentRequest}
     */
    public function verify(ConsentRequest $consent, string $code): array
    {
        if ($consent->status !== ConsentStatus::Pending) {
            return $this->result(false, 'This consent request is no longer active. Please request a new code.', $consent);
        }

        if ($consent->isOtpExpired()) {
            $consent->status = ConsentStatus::Expired;
            $consent->save();

            return $this->result(false, 'The code has expired. Please request a new one.', $consent);
        }

        // Count the attempt before checking, so a wrong guess always costs one.
        $consent->increment('attempts');

        if (! Hash::check($code, $consent->otp_hash)) {
            if (! $consent->hasAttemptsLeft()) {
                $consent->status = ConsentStatus::Failed;
                $consent->save();
                AuditLog::record('consent.failed', $consent);

                return $this->result(false, 'Too many incorrect attempts. Please start a new consent request.', $consent);
            }

            return $this->result(false, 'That code is incorrect. Please try again.', $consent);
        }

        // Assigned directly rather than mass-assigned: the grant window is a
        // security decision this service owns, never something a request body
        // should be able to reach.
        $consent->status = ConsentStatus::Verified;
        $consent->verified_at = Carbon::now();
        $consent->grant_expires_at = Carbon::now()
            ->addMinutes((int) config('goldscore.consent.grant_ttl_minutes'));
        $consent->save();

        AuditLog::record('consent.granted', $consent, [
            'customer_id' => $consent->customer_id,
            'valid_until' => $consent->grant_expires_at->toIso8601String(),
        ]);

        return $this->result(true, 'Consent granted.', $consent);
    }

    public function activeGrant(Customer $customer, int $storeId): ?ConsentRequest
    {
        return ConsentRequest::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->activeGrant($customer->id)
            ->latest('verified_at')
            ->first();
    }

    public function pendingRequest(Customer $customer, int $storeId): ?ConsentRequest
    {
        return ConsentRequest::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('customer_id', $customer->id)
            ->where('status', ConsentStatus::Pending->value)
            ->where('otp_expires_at', '>', Carbon::now())
            ->latest()
            ->first();
    }

    public function channelDescription(): string
    {
        return $this->channel->describe();
    }

    private function result(bool $ok, string $message, ConsentRequest $consent): array
    {
        return ['ok' => $ok, 'message' => $message, 'consent' => $consent->refresh()];
    }
}
