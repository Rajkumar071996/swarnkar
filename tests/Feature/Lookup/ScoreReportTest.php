<?php

namespace Tests\Feature\Lookup;

use App\Enums\DefaultFlagReason;
use App\Models\Customer;
use App\Models\DefaultFlag;
use App\Models\Store;
use App\Models\Udhaar;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the counter flow end to end through the rendered screens. Model strict
 * mode is on in tests, so these also catch a view that reaches for a relation
 * the controller forgot to load.
 */
class ScoreReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Store $rivalStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->owner()->create();
        $this->customer = Customer::factory()->named('Rajesh Kumar')->withMobile('9829100001')->create([
            'address_line' => '12 Johari Bazaar',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302003',
        ]);
        $this->customer->stores()->attach($this->user->store_id, ['first_seen_at' => now()]);

        $this->rivalStore = Store::factory()->create([
            'name' => 'Mahalaxmi Jewellers',
            'city' => 'Ajmer',
            'state' => 'Rajasthan',
        ]);

        $this->giveCustomerHistory();
    }

    #[Test]
    public function searching_a_mobile_number_finds_the_customer(): void
    {
        $this->actingAs($this->user)
            ->post(route('lookup.search'), ['q' => '9829100001'])
            ->assertOk()
            ->assertSee('Rajesh Kumar');
    }

    #[Test]
    public function a_search_that_is_too_short_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('lookup.search'), ['q' => '98'])
            ->assertSessionHasErrors('q');
    }

    #[Test]
    public function the_report_shows_the_consent_screen_until_a_code_is_verified(): void
    {
        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('consent', false)
            ->assertDontSee('Recommended credit limit');
    }

    #[Test]
    public function the_whole_counter_flow_renders_the_score_report(): void
    {
        $this->actingAs($this->user)
            ->post(route('lookup.consent.request', $this->customer))
            ->assertRedirect(route('lookup.report', $this->customer));

        $this->actingAs($this->user)
            ->post(route('lookup.consent.verify', $this->customer), ['code' => '9999'])
            ->assertRedirect(route('lookup.report', $this->customer))
            ->assertSessionHas('success');

        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('Rajesh Kumar')
            ->assertSee('12 Johari Bazaar, Jaipur, Rajasthan, 302003')
            ->assertSee('Store credit settlement')
            ->assertSee('Headroom to lend');
    }

    #[Test]
    public function the_report_warns_that_the_customer_owes_money_at_another_jeweller(): void
    {
        $this->grantConsent();

        // The scenario the product exists for: nothing overdue in your own book,
        // 50,000 sitting unpaid at a shop you cannot see into.
        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertSee('Already owes '.money(50000), false)
            ->assertSee('1', false)
            ->assertSee('other jeweller')
            // Anonymised, and never confused with what he owes the shop asking.
            ->assertSee('Ajmer')
            ->assertDontSee('Mahalaxmi');
    }

    #[Test]
    public function the_khata_screens_render_for_a_customer_with_history(): void
    {
        // His own-store entry is settled, so he is absent from the default
        // "with balance" view and present once the filter is widened.
        $this->actingAs($this->user)
            ->get(route('khata.index'))
            ->assertOk()
            ->assertDontSee('Rajesh Kumar');

        $this->actingAs($this->user)
            ->get(route('khata.index', ['filter' => 'all']))
            ->assertOk()
            ->assertSee('Rajesh Kumar');

        $this->actingAs($this->user)
            ->get(route('khata.show', $this->customer))
            ->assertOk()
            ->assertSee('Credit entries')
            ->assertSee('Payment history');
    }

    #[Test]
    public function the_khata_hides_cross_store_exposure_until_consent_is_granted(): void
    {
        $this->actingAs($this->user)
            ->get(route('khata.show', $this->customer))
            ->assertOk()
            ->assertDontSee('Position across the network')
            ->assertSee('Run a consented credit check');

        $this->grantConsent();

        $this->actingAs($this->user)
            ->get(route('khata.show', $this->customer))
            ->assertOk()
            ->assertSee('Position across the network');
    }

    #[Test]
    public function the_report_anonymises_the_other_merchants_on_the_network(): void
    {
        $this->grantConsent();

        $response = $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk();

        // The city is shown so the jeweller knows the reach of the history,
        // but never which shop it came from.
        $response->assertSee('Ajmer')->assertDontSee('Mahalaxmi Jewellers');
    }

    #[Test]
    public function a_wrong_code_leaves_the_report_closed(): void
    {
        $this->actingAs($this->user)->post(route('lookup.consent.request', $this->customer));

        $this->actingAs($this->user)
            ->post(route('lookup.consent.verify', $this->customer), ['code' => '1234'])
            ->assertSessionHas('error');

        $this->actingAs($this->user)
            ->get(route('lookup.report', $this->customer))
            ->assertOk()
            ->assertDontSee('Recommended credit limit');
    }

    #[Test]
    public function verifying_with_no_request_in_flight_asks_for_a_new_code(): void
    {
        $this->actingAs($this->user)
            ->post(route('lookup.consent.verify', $this->customer), ['code' => '9999'])
            ->assertSessionHas('error');
    }

    #[Test]
    public function viewing_a_score_is_written_to_the_audit_trail(): void
    {
        $this->grantConsent();

        $this->actingAs($this->user)->get(route('lookup.report', $this->customer))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'score.viewed',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function the_customer_profile_screen_renders_its_full_history(): void
    {
        $this->actingAs($this->user)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee('Rajesh Kumar');
    }

    #[Test]
    public function the_ledger_detail_screens_render(): void
    {
        $udhaar = Udhaar::where('store_id', $this->user->store_id)->first();

        $this->actingAs($this->user)->get(route('udhaars.show', $udhaar))->assertOk();
        $this->actingAs($this->user)->get(route('udhaars.index'))->assertOk();
    }

    private function grantConsent(): void
    {
        $service = app(ConsentService::class);
        $service->verify($service->issue($this->customer, $this->user), '9999');
    }

    private function giveCustomerHistory(): void
    {
        Udhaar::factory()->issuedOn(now()->subMonths(4))->settledOnTime()->create([
            'store_id' => $this->user->store_id,
            'customer_id' => $this->customer->id,
            'principal_amount' => 80000,
        ]);

        // Still owed at the rival store, so the report has to both anonymise the
        // merchant and surface the exposure.
        Udhaar::factory()->issuedOn(now()->subMonths(3))->create([
            'store_id' => $this->rivalStore->id,
            'customer_id' => $this->customer->id,
            'principal_amount' => 50000,
        ]);

        DefaultFlag::factory()->verified()->reason(DefaultFlagReason::BouncedCheque)->create([
            'store_id' => $this->rivalStore->id,
            'customer_id' => $this->customer->id,
        ]);
    }
}
