<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Udhaar;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the shape of the /api/v1 contract the Flutter client will be written
 * against, so a change here has to be a deliberate one.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
        $this->customer = Customer::factory()->named('Rajesh Kumar')->withMobile('9876543210')->create();
        $this->customer->stores()->attach($this->owner->store_id, ['first_seen_at' => now()]);
    }

    #[Test]
    public function signing_in_returns_a_bearer_token_and_the_users_store(): void
    {
        $response = $this->postJson(route('api.auth.login'), [
            'phone' => $this->owner->phone,
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'phone', 'role', 'store' => ['id', 'name']],
        ]);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function bad_credentials_do_not_hand_out_a_token(): void
    {
        $this->postJson(route('api.auth.login'), [
            'phone' => $this->owner->phone,
            'password' => 'wrong-password',
            'device_name' => 'Pixel 8',
        ])->assertStatus(422);
    }

    #[Test]
    public function a_deactivated_account_is_refused_a_token(): void
    {
        $this->owner->update(['is_active' => false]);

        $this->postJson(route('api.auth.login'), [
            'phone' => $this->owner->phone,
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ])->assertStatus(422);
    }

    #[Test]
    public function every_endpoint_behind_the_gate_rejects_an_anonymous_caller(): void
    {
        $this->getJson(route('api.customers.index'))->assertUnauthorized();
        $this->getJson(route('api.udhaars.index'))->assertUnauthorized();
        $this->getJson(route('api.khata.index'))->assertUnauthorized();
        $this->getJson(route('api.customers.exposure', $this->customer))->assertUnauthorized();
        $this->getJson(route('api.lookup.score', $this->customer))->assertUnauthorized();
    }

    #[Test]
    public function a_token_belonging_to_a_deactivated_user_stops_working(): void
    {
        Sanctum::actingAs($this->owner);
        $this->getJson(route('api.customers.index'))->assertOk();

        $this->owner->update(['is_active' => false]);
        $this->getJson(route('api.customers.index'))->assertForbidden();
    }

    #[Test]
    public function search_returns_masked_identifiers_only(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson(route('api.lookup.search'), ['q' => '9876543210']);

        $response->assertOk()->assertJsonPath('data.0.full_name', 'Rajesh Kumar');

        $body = $response->getContent();
        $this->assertStringNotContainsString('9876543210', $body, 'A full mobile number must never leave the API.');
        $this->assertStringContainsString('3210', $body);
    }

    #[Test]
    public function a_score_cannot_be_read_without_consent(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson(route('api.lookup.score', $this->customer))
            ->assertForbidden()
            ->assertJsonPath('consent_required', true);
    }

    #[Test]
    public function the_full_consent_and_score_round_trip_works(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson(route('api.lookup.consent', $this->customer))
            ->assertCreated()
            ->assertJsonStructure(['consent_request_id', 'expires_at', 'delivery']);

        $this->postJson(route('api.lookup.verify', $this->customer), ['code' => '9999'])
            ->assertOk()
            ->assertJsonPath('granted', true);

        $this->getJson(route('api.lookup.score', $this->customer))
            ->assertOk()
            ->assertJsonStructure([
                'customer' => ['id', 'full_name'],
                'score' => ['score', 'band', 'recommended_credit_limit', 'components'],
                'exposure' => ['total_outstanding', 'at_your_store', 'at_other_stores', 'stores'],
                'consent_expires_at',
            ]);
    }

    #[Test]
    public function the_khata_endpoint_returns_one_row_per_customer_account(): void
    {
        Sanctum::actingAs($this->owner);

        Udhaar::factory()->count(2)->create([
            'store_id' => $this->owner->store_id,
            'customer_id' => $this->customer->id,
            'principal_amount' => 20000,
        ]);

        $this->getJson(route('api.khata.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entries', 2)
            ->assertJsonPath('data.0.outstanding', 40000)
            ->assertJsonPath('data.0.customer.full_name', 'Rajesh Kumar');

        $this->getJson(route('api.khata.show', $this->customer))
            ->assertOk()
            ->assertJsonPath('summary.entries', 2)
            ->assertJsonPath('summary.outstanding', 40000)
            ->assertJsonCount(2, 'entries');
    }

    #[Test]
    public function network_exposure_needs_consent_and_then_names_no_competitor(): void
    {
        $rivalStore = Store::factory()->create([
            'name' => 'Mahalaxmi Jewellers',
            'address_line' => '44 Diggi Bazaar',
            'city' => 'Ajmer',
            'state' => 'Rajasthan',
            'pincode' => '305001',
        ]);

        Udhaar::factory()->create([
            'store_id' => $rivalStore->id,
            'customer_id' => $this->customer->id,
            'principal_amount' => 50000,
        ]);

        Sanctum::actingAs($this->owner);

        $this->getJson(route('api.customers.exposure', $this->customer))
            ->assertForbidden()
            ->assertJsonPath('consent_required', true);

        $this->postJson(route('api.lookup.consent', $this->customer));
        $this->postJson(route('api.lookup.verify', $this->customer), ['code' => '9999'])->assertOk();

        $response = $this->getJson(route('api.customers.exposure', $this->customer))
            ->assertOk()
            ->assertJsonPath('exposure.at_other_stores', 50000)
            ->assertJsonPath('exposure.at_your_store', 0)
            ->assertJsonPath('exposure.other_store_count', 1);

        $this->assertStringNotContainsString('Mahalaxmi', $response->getContent());
        $this->assertStringContainsString('Ajmer', $response->getContent());
    }

    #[Test]
    public function a_wrong_code_does_not_open_the_consent_window(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson(route('api.lookup.consent', $this->customer))->assertCreated();

        $this->postJson(route('api.lookup.verify', $this->customer), ['code' => '1234'])
            ->assertStatus(422)
            ->assertJsonPath('granted', false);

        $this->getJson(route('api.lookup.score', $this->customer))->assertForbidden();
    }

    #[Test]
    public function verifying_without_a_pending_request_reports_it_as_gone(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson(route('api.lookup.verify', $this->customer), ['code' => '9999'])
            ->assertStatus(410);
    }

    #[Test]
    public function consent_granted_to_one_store_does_not_carry_to_another(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson(route('api.lookup.consent', $this->customer));
        $this->postJson(route('api.lookup.verify', $this->customer), ['code' => '9999'])->assertOk();

        $rival = User::factory()->owner()->create(['store_id' => Store::factory()->create()->id]);
        Sanctum::actingAs($rival);

        $this->getJson(route('api.lookup.score', $this->customer))->assertForbidden();
    }

    #[Test]
    public function credit_can_be_issued_and_then_paid_down_over_the_api(): void
    {
        Sanctum::actingAs($this->owner);

        $created = $this->postJson(route('api.udhaars.store'), [
            'customer_id' => $this->customer->id,
            'item_description' => '22K gold chain',
            'principal_amount' => 45000,
            'issued_on' => Carbon::today()->toDateString(),
            'due_on' => Carbon::today()->addDays(30)->toDateString(),
        ])->assertCreated();

        $id = $created->json('data.id');

        $paid = $this->postJson(route('api.udhaars.payments', $id), [
            'amount' => 15000,
            'paid_on' => Carbon::today()->toDateString(),
            'method' => 'upi',
        ])->assertOk()->assertJsonPath('data.status', 'partially_paid');

        // Compared numerically because JSON gives back a whole number as an int.
        $this->assertSame(30000.0, (float) $paid->json('data.outstanding_amount'));
    }

    #[Test]
    public function an_overpayment_is_rejected_with_a_validation_error(): void
    {
        Sanctum::actingAs($this->owner);
        $udhaar = Udhaar::factory()->create([
            'store_id' => $this->owner->store_id,
            'customer_id' => $this->customer->id,
            'principal_amount' => 10000,
        ]);

        $this->postJson(route('api.udhaars.payments', $udhaar), [
            'amount' => 10001,
            'paid_on' => Carbon::today()->toDateString(),
            'method' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    #[Test]
    public function staff_may_record_a_payment_but_not_issue_new_credit(): void
    {
        $staff = User::factory()->staff()->create(['store_id' => $this->owner->store_id]);
        Sanctum::actingAs($staff);

        $this->postJson(route('api.udhaars.store'), [
            'customer_id' => $this->customer->id,
            'item_description' => '22K gold chain',
            'principal_amount' => 45000,
            'issued_on' => Carbon::today()->toDateString(),
            'due_on' => Carbon::today()->addDays(30)->toDateString(),
        ])->assertForbidden();

        $udhaar = Udhaar::factory()->create([
            'store_id' => $staff->store_id,
            'customer_id' => $this->customer->id,
            'principal_amount' => 10000,
        ]);

        $this->postJson(route('api.udhaars.payments', $udhaar), [
            'amount' => 1000,
            'paid_on' => Carbon::today()->toDateString(),
            'method' => 'cash',
        ])->assertOk();
    }

    #[Test]
    public function the_ledger_endpoint_never_returns_another_stores_rows(): void
    {
        Udhaar::factory()->create([
            'store_id' => $this->owner->store_id,
            'customer_id' => $this->customer->id,
        ]);
        $rivalRow = Udhaar::factory()->create(['store_id' => Store::factory()->create()->id]);

        Sanctum::actingAs($this->owner);

        $this->getJson(route('api.udhaars.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson(route('api.udhaars.show', $rivalRow))->assertNotFound();
    }

    #[Test]
    public function logging_out_revokes_the_token_that_made_the_call(): void
    {
        $token = $this->postJson(route('api.auth.login'), [
            'phone' => $this->owner->phone,
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ])->json('token');

        $this->withToken($token)->postJson(route('api.auth.logout'))->assertOk();

        $this->assertSame(0, $this->owner->tokens()->count());
    }

    #[Test]
    public function the_grant_from_the_web_flow_is_honoured_by_the_api(): void
    {
        // A jeweller who took consent at the counter should not be asked again
        // when the same store opens the record on a phone.
        $consent = app(ConsentService::class)->issue($this->customer, $this->owner);
        app(ConsentService::class)->verify($consent, '9999');

        Sanctum::actingAs($this->owner);

        $this->getJson(route('api.lookup.score', $this->customer))->assertOk();
    }
}
