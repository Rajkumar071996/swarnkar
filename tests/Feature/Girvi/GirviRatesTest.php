<?php

namespace Tests\Feature\Girvi;

use App\Models\Customer;
use App\Models\MetalRate;
use App\Models\User;
use App\Services\Girvi\MetalRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GirviRatesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-10');

        $this->owner = User::factory()->owner()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_owner_can_set_the_gold_and_silver_rate(): void
    {
        $this->actingAs($this->owner)
            ->put(route('girvi.settings.rates'), ['gold' => 9450.50, 'silver' => 112.25])
            ->assertRedirect();

        $this->assertSame(
            ['gold' => 9450.50, 'silver' => 112.25],
            app(MetalRates::class)->current($this->owner->store_id),
        );
    }

    #[Test]
    public function saving_twice_in_a_day_corrects_the_day_rather_than_stacking_rows(): void
    {
        $this->actingAs($this->owner)->put(route('girvi.settings.rates'), ['gold' => 9000, 'silver' => 100]);
        $this->actingAs($this->owner)->put(route('girvi.settings.rates'), ['gold' => 9200, 'silver' => 105]);

        $this->assertSame(2, MetalRate::query()->networkWide()->count());
        $this->assertSame(9200.0, app(MetalRates::class)->current($this->owner->store_id)['gold']);
    }

    #[Test]
    public function yesterdays_rate_stays_on_the_record(): void
    {
        $rates = app(MetalRates::class);

        $rates->set($this->owner->store_id, 'gold', 8800, $this->owner, Carbon::parse('2026-08-09'));
        $rates->set($this->owner->store_id, 'gold', 9100, $this->owner, Carbon::parse('2026-08-10'));

        $this->assertSame(9100.0, $rates->current($this->owner->store_id)['gold']);
        $this->assertSame(8800.0, $rates->current($this->owner->store_id, Carbon::parse('2026-08-09'))['gold']);

        $this->actingAs($this->owner)
            ->get(route('girvi.settings.edit'))
            ->assertOk()
            ->assertSee('value="9100"', false)
            ->assertSee('₹8,800');
    }

    #[Test]
    public function a_shop_that_has_never_set_a_rate_falls_back_to_the_default(): void
    {
        $this->assertSame(
            (float) config('girvi.rate_per_gram.gold'),
            app(MetalRates::class)->current($this->owner->store_id)['gold'],
        );
    }

    #[Test]
    public function the_new_girvi_screen_prefills_both_rates(): void
    {
        $customer = Customer::factory()->create();
        $customer->stores()->attach($this->owner->store_id, ['first_seen_at' => now()]);

        $this->actingAs($this->owner)->put(route('girvi.settings.rates'), ['gold' => 9450, 'silver' => 112]);

        $this->actingAs($this->owner)
            ->get(route('girvi.loans.create'))
            ->assertOk()
            ->assertSee('value="9450"', false)
            ->assertSee('value="112"', false)
            ->assertSee('data-metal="gold"', false)
            ->assertSee('data-metal="silver"', false);
    }

    #[Test]
    public function staff_cannot_change_what_the_shop_lends_against(): void
    {
        $staff = User::factory()->staff()->create(['store_id' => $this->owner->store_id]);

        $this->actingAs($staff)->get(route('girvi.settings.edit'))->assertForbidden();

        $this->actingAs($staff)
            ->put(route('girvi.settings.rates'), ['gold' => 1, 'silver' => 1])
            ->assertForbidden();
    }

    #[Test]
    public function one_shops_rate_does_not_leak_into_another(): void
    {
        $other = User::factory()->owner()->create();

        $this->actingAs($this->owner)->put(route('girvi.settings.rates'), ['gold' => 9450, 'silver' => 112]);

        $this->assertSame(
            (float) config('girvi.rate_per_gram.gold'),
            app(MetalRates::class)->current($other->store_id)['gold'],
        );
    }
}
