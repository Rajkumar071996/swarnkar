<?php

namespace Tests\Feature\Dashboard;

use App\Models\Customer;
use App\Models\User;
use App\Services\KhataAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardAdvanceStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_dashboard_shows_advance_stats_for_the_store(): void
    {
        $user = User::factory()->owner()->create();
        $customer = Customer::factory()->named('Meena Sharma')->create();
        $customer->stores()->attach($user->store_id, ['first_seen_at' => now()]);

        app(KhataAdvanceService::class)->credit(
            $customer,
            $user->store_id,
            12000,
            Carbon::today(),
            'cash',
            null,
            $user,
            'Booking advance',
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Advances held')
            ->assertSee(money(12000), false)
            ->assertSee('1 customer')
            ->assertSee('Meena Sharma');
    }
}
