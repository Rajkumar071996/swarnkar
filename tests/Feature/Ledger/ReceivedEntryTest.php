<?php

namespace Tests\Feature\Ledger;

use App\Models\Customer;
use App\Models\KhataAdvance;
use App\Models\Udhaar;
use App\Models\User;
use App\Services\KhataAdvanceService;
use App\Services\UdhaarLedger;
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
    }

    #[Test]
    public function the_received_entry_screen_lists_linked_customers_without_outstanding(): void
    {
        $this->actingAs($this->user)
            ->get(route('khata.receive'))
            ->assertOk()
            ->assertSee('Select a customer')
            ->assertSee($this->customer->full_name)
            ->assertDontSee('Select a customer with outstanding balance');
    }

    #[Test]
    public function the_received_entry_form_works_when_nothing_is_outstanding(): void
    {
        $this->actingAs($this->user)
            ->get(route('khata.receive.customer', $this->customer))
            ->assertOk()
            ->assertSee('Received entry')
            ->assertSee('Amount received')
            ->assertSee('Apply against')
            ->assertSee('name="remark"', false)
            ->assertSee('saved as advance')
            ->assertSee($this->customer->full_name);
    }

    #[Test]
    public function a_received_entry_reduces_the_khata_balance(): void
    {
        Udhaar::factory()
            ->issuedOn(Carbon::today()->subDays(40))
            ->create([
                'store_id' => $this->user->store_id,
                'customer_id' => $this->customer->id,
                'principal_amount' => 40000,
            ]);

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

    #[Test]
    public function receiving_with_no_bills_creates_an_advance(): void
    {
        $this->actingAs($this->user)
            ->post(route('khata.receive.store', $this->customer), [
                'amount' => 10000,
                'paid_on' => Carbon::today()->toDateString(),
                'method' => 'upi',
                'reference' => 'ADV1',
                'remark' => 'Booking advance for necklace',
            ])
            ->assertRedirect(route('khata.show', $this->customer))
            ->assertSessionHas('success');

        $this->assertSame(0, Udhaar::count());
        $this->assertSame(10000.0, app(KhataAdvanceService::class)->balance(
            $this->customer,
            $this->user->store_id,
        ));
        $this->assertDatabaseHas('khata_advance_entries', [
            'customer_id' => $this->customer->id,
            'store_id' => $this->user->store_id,
            'notes' => 'Booking advance for necklace',
        ]);

        $this->actingAs($this->user)
            ->get(route('khata.show', $this->customer))
            ->assertOk()
            ->assertSee('Advance with you')
            ->assertSee('Booking advance for necklace');
    }

    #[Test]
    public function receiving_more_than_outstanding_splits_into_payment_and_advance(): void
    {
        Udhaar::factory()
            ->issuedOn(Carbon::today()->subDays(10))
            ->create([
                'store_id' => $this->user->store_id,
                'customer_id' => $this->customer->id,
                'principal_amount' => 20000,
            ]);

        $result = app(UdhaarLedger::class)->receive(
            $this->customer,
            25000,
            Carbon::today(),
            'cash',
            null,
            $this->user,
        );

        $this->assertCount(1, $result['payments']);
        $this->assertSame(5000.0, $result['advance_credited']);
        $this->assertSame(0.0, Udhaar::first()->fresh()->outstandingAmount());
        $this->assertSame(5000.0, app(KhataAdvanceService::class)->balance(
            $this->customer,
            $this->user->store_id,
        ));
    }

    #[Test]
    public function issuing_credit_auto_applies_available_advance(): void
    {
        app(KhataAdvanceService::class)->credit(
            $this->customer,
            $this->user->store_id,
            15000,
            Carbon::today(),
            'cash',
            null,
            $this->user,
        );

        $udhaar = app(UdhaarLedger::class)->issue([
            'customer_id' => $this->customer->id,
            'item_description' => 'Necklace',
            'principal_amount' => 40000,
            'issued_on' => Carbon::today(),
            'due_on' => Carbon::today()->addDays(30),
        ], $this->user);

        $this->assertSame(25000.0, $udhaar->fresh()->outstandingAmount());
        $this->assertSame(0.0, app(KhataAdvanceService::class)->balance(
            $this->customer,
            $this->user->store_id,
        ));
        $this->assertDatabaseHas('khata_advances', [
            'store_id' => $this->user->store_id,
            'customer_id' => $this->customer->id,
            'balance' => 0,
        ]);
        $this->assertTrue(KhataAdvance::query()->exists());
    }
}
