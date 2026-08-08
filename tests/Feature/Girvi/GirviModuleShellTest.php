<?php

namespace Tests\Feature\Girvi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GirviModuleShellTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_goldscore_menu_shows_everywhere_outside_the_girvi_routes(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Check GoldScore')
            ->assertSee('Udhar Khata')
            ->assertDontSee('All Mortgage');
    }

    #[Test]
    public function the_girvi_routes_swap_the_sidebar_for_the_girvi_menu(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('girvi.dashboard'))
            ->assertOk()
            ->assertSee('New Girvi')
            ->assertSee('All Mortgage')
            ->assertSee('UnReleased')
            ->assertDontSee('Udhar Khata');
    }

    #[Test]
    public function the_shared_customer_screens_stay_in_whichever_module_you_came_from(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->get(route('girvi.dashboard'))->assertOk();

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('All Mortgage')
            ->assertDontSee('Udhar Khata');

        // Back through a GoldScore screen and the customer list follows.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Udhar Khata')
            ->assertDontSee('All Mortgage');
    }

    #[Test]
    public function both_menus_offer_the_switcher_to_the_other_module(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee(route('girvi.dashboard'), false);

        $this->actingAs($user)->get(route('girvi.dashboard'))
            ->assertSee(route('dashboard'), false);
    }
}
