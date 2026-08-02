<?php

namespace Tests\Feature\Ledger;

use App\Models\Customer;
use App\Models\Udhaar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceivedEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->owner()->create();
        $this->customer = Customer::factory()->create();
        $this->customer->stores()->attach($this->user->store_id, ['first_seen_at' => now()]);

        Udhaar::factory()
            ->issuedOn(Carbon::today()->subDays(40))
            ->create([
                'store_id' => $this->user->store_id,
                'customer_id' => $this->customer->id,
                'principal_amount' => 40000,
            ]);
    }

    #[Test]
    public function the_received_entry_screen_is_reachable_from_the_khata(): void
    {
        $this->actingAs($this->user)
            ->get(route('khata.receive.customer', $this->customer))
            ->assertOk()
            ->assertSee('Received entry')
            ->assertSee('Amount received')
            ->assertSee($this->customer->full_name);
    }

    #[Test]
    public function a_received_entry_reduces_the_khata_balance(): void
    {
        $this->actingAs($this->user)
            ->post(route('khata.receive.store', $this->customer), [
                'amount' => 15000,
                'paid_on' => Carbon::today()->toDateString(),
                'method' => 'cash',
            ])
            ->assertRedirect(route('khata.show', $this->customer))
            ->assertSessionHas('success');

        $this->assertSame(25000.0, Udhaar::first()->fresh()->outstandingAmount());
    }
}
