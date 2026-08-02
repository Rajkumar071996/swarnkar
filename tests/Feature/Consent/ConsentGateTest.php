<?php

namespace Tests\Feature\Consent;

use App\Enums\ConsentStatus;
use App\Models\ConsentRequest;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsentGateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->owner()->create();
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function the_report_is_withheld_until_the_customer_consents(): void
    {
        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('Customer consent required')
            ->assertDontSee('How this score was built');
    }

    #[Test]
    public function the_static_channel_issues_the_configured_development_code(): void
    {
        config()->set('goldscore.otp.static_code', '9999');

        $this->actingAs($this->user)
            ->post(route('lookup.consent.request', $this->customer))
            ->assertRedirect(route('lookup.report', $this->customer));

        $this->actingAs($this->user)
            ->post(route('lookup.consent.verify', $this->customer), ['code' => '9999'])
            ->assertRedirect(route('lookup.report', $this->customer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('consent_requests', [
            'customer_id' => $this->customer->id,
            'status' => ConsentStatus::Verified->value,
        ]);
    }

    #[Test]
    public function the_otp_is_never_stored_in_readable_form(): void
    {
        $consent = app(ConsentService::class)->issue($this->customer, $this->user);

        $this->assertNotSame('9999', $consent->otp_hash);
        $this->assertStringNotContainsString('9999', $consent->otp_hash);
    }

    #[Test]
    public function a_verified_consent_unlocks_the_score_report(): void
    {
        $this->grantConsent();

        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('How this score was built');
    }

    #[Test]
    public function consent_expires_and_the_report_closes_again(): void
    {
        $this->grantConsent();

        $this->travel(config('goldscore.consent.grant_ttl_minutes') + 1)->minutes();

        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('Customer consent required');
    }

    #[Test]
    public function consent_granted_to_one_store_does_not_unlock_the_report_at_another(): void
    {
        $this->grantConsent();

        $otherStoreUser = User::factory()->owner()->create(['store_id' => Store::factory()->create()->id]);

        $this->actingAs($otherStoreUser)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('Customer consent required');
    }

    #[Test]
    public function a_wrong_code_is_rejected_and_costs_an_attempt(): void
    {
        $consent = app(ConsentService::class)->issue($this->customer, $this->user);

        $outcome = app(ConsentService::class)->verify($consent, '1234');

        $this->assertFalse($outcome['ok']);
        $this->assertSame(1, $consent->fresh()->attempts);
        $this->assertSame(ConsentStatus::Pending, $consent->fresh()->status);
    }

    #[Test]
    public function the_request_fails_permanently_after_too_many_wrong_codes(): void
    {
        $service = app(ConsentService::class);
        $consent = $service->issue($this->customer, $this->user);
        $maxAttempts = (int) config('goldscore.consent.max_attempts');

        for ($i = 0; $i < $maxAttempts; $i++) {
            $service->verify($consent->fresh(), '1234');
        }

        $this->assertSame(ConsentStatus::Failed, $consent->fresh()->status);

        // Even the correct code must not revive a burned-out request.
        $outcome = $service->verify($consent->fresh(), '9999');
        $this->assertFalse($outcome['ok']);
    }

    #[Test]
    public function an_expired_code_cannot_be_used(): void
    {
        $service = app(ConsentService::class);
        $consent = $service->issue($this->customer, $this->user);

        $this->travel(config('goldscore.consent.otp_ttl_minutes') + 1)->minutes();

        $outcome = $service->verify($consent->fresh(), '9999');

        $this->assertFalse($outcome['ok']);
        $this->assertSame(ConsentStatus::Expired, $consent->fresh()->status);
    }

    #[Test]
    public function issuing_a_new_code_supersedes_the_previous_pending_request(): void
    {
        $service = app(ConsentService::class);
        $first = $service->issue($this->customer, $this->user);
        $service->issue($this->customer, $this->user);

        $this->assertSame(ConsentStatus::Expired, $first->fresh()->status);
    }

    #[Test]
    public function every_score_disclosure_leaves_an_audit_trail(): void
    {
        $this->grantConsent();

        $this->actingAs($this->user)->get(route('lookup.report', $this->customer));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'score.viewed',
            'subject_id' => $this->customer->id,
        ]);
    }

    private function grantConsent(): ConsentRequest
    {
        $service = app(ConsentService::class);
        $consent = $service->issue($this->customer, $this->user);
        $service->verify($consent, '9999');

        $this->assertTrue($consent->fresh()->grantsAccess());

        return $consent->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
