<?php

namespace Tests\Feature\Girvi;

use App\Enums\GoldLoanStatus;
use App\Events\CustomerLedgerChanged;
use App\Models\Customer;
use App\Models\GoldLoan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GirviReleaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private GoldLoan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15');

        $this->user = User::factory()->owner()->create();
        $this->customer = Customer::factory()->create();
        $this->customer->stores()->attach($this->user->store_id, ['first_seen_at' => now()]);

        $this->loan = GoldLoan::factory()->create([
            'store_id' => $this->user->store_id,
            'customer_id' => $this->customer->id,
            'receipt_no' => 'GRT-19/27-1',
            'principal_amount' => 40000,
            'interest_rate' => 60,
            'gross_weight_grams' => 12.5,
            'less_weight_grams' => 2.5,
            'net_weight_grams' => 10,
            'fine_weight_grams' => 9.16,
            'total_value' => 54960,
            'estimate_percent' => 75,
            'estimate_amount' => 41220,
            'disbursed_on' => '2026-01-01',
            'due_on' => '2026-07-01',
            'status' => GoldLoanStatus::Active,
        ]);

        $this->loan->items()->create([
            'metal_type' => 'gold',
            'item_type' => 'Chain',
            'quantity' => 1,
            'gross_weight_grams' => 12.5,
            'less_weight_grams' => 2.5,
            'net_weight_grams' => 10,
            'weight_percent' => 91.6,
            'fine_weight_grams' => 9.16,
            'rate_per_gram' => 6000,
            'total_amount' => 54960,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_release_screen_finds_a_pledge_by_receipt_number(): void
    {
        $this->actingAs($this->user)
            ->get(route('girvi.release.create', ['q' => 'GRT-19/27-1']))
            ->assertOk()
            ->assertSee($this->customer->full_name);
    }

    #[Test]
    public function the_settlement_charges_a_part_month_as_a_full_month(): void
    {
        $this->actingAs($this->user)
            ->get(route('girvi.release.create', ['loan' => $this->loan->id]))
            ->assertOk()
            ->assertSee('Interest is charged for 3')
            // Three months to 15 March on 40,000 at 60 percent a year.
            ->assertSee('₹6,000')
            ->assertSee('₹46,000');
    }

    #[Test]
    public function releasing_nets_the_extra_charges_and_hands_the_jewellery_back(): void
    {
        $this->actingAs($this->user)
            ->post(route('girvi.release.store', $this->loan), [
                'released_on' => '2026-03-15',
                'extra_amount' => 500,
                'notice_charge' => 100,
                'discount' => 200,
            ])
            ->assertRedirect(route('girvi.release.receipt', $this->loan));

        $this->loan->refresh();

        $this->assertSame('2026-03-15', $this->loan->released_on->toDateString());
        $this->assertSame(GoldLoanStatus::Closed, $this->loan->status);
        $this->assertSame('40000.00', $this->loan->principal_repaid);
        $this->assertSame('6000.00', $this->loan->interest_collected);

        // 40,000 principal plus 6,000 interest plus 500 extra plus 100 notice less 200 discount.
        $this->assertSame('46400.00', $this->loan->payments()->where('type', 'principal')->value('amount'));
        $this->assertSame('GRS-19/27-1', $this->loan->payments()->where('type', 'principal')->value('receipt_no'));
    }

    #[Test]
    public function interest_already_collected_comes_off_the_settlement(): void
    {
        $this->actingAs($this->user)->post(route('girvi.loans.interest', $this->loan), [
            'amount' => 2000,
            'paid_on' => '2026-02-01',
            'method' => 'cash',
        ]);

        $this->actingAs($this->user)->post(route('girvi.release.store', $this->loan), [
            'released_on' => '2026-03-15',
        ]);

        $this->assertSame('44000.00', $this->loan->payments()->where('type', 'principal')->value('amount'));
        $this->assertSame('6000.00', $this->loan->refresh()->interest_collected);
    }

    #[Test]
    public function a_released_pledge_moves_to_the_released_list_and_prints_its_receipt(): void
    {
        $this->actingAs($this->user)->post(route('girvi.release.store', $this->loan), [
            'released_on' => '2026-03-15',
        ]);

        $this->actingAs($this->user)
            ->get(route('girvi.loans.index', ['filter' => 'released']))
            ->assertOk()
            ->assertSee($this->customer->full_name);

        $this->actingAs($this->user)
            ->get(route('girvi.loans.index', ['filter' => 'unreleased']))
            ->assertOk()
            ->assertDontSee($this->customer->full_name);

        $this->actingAs($this->user)
            ->get(route('girvi.release.receipt', $this->loan))
            ->assertOk()
            ->assertSee('Release Receipt')
            ->assertSee('GRS-19/27-1');
    }

    #[Test]
    public function the_same_pledge_cannot_be_released_twice(): void
    {
        $payload = ['released_on' => '2026-03-15'];

        $this->actingAs($this->user)->post(route('girvi.release.store', $this->loan), $payload);

        $this->actingAs($this->user)
            ->post(route('girvi.release.store', $this->loan), $payload)
            ->assertSessionHasErrors('release');
    }

    #[Test]
    public function staff_can_collect_interest_but_cannot_release(): void
    {
        $staff = User::factory()->staff()->create(['store_id' => $this->user->store_id]);

        $this->actingAs($staff)
            ->post(route('girvi.loans.interest', $this->loan), [
                'amount' => 1000,
                'paid_on' => '2026-03-01',
                'method' => 'cash',
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->post(route('girvi.release.store', $this->loan), ['released_on' => '2026-03-15'])
            ->assertForbidden();
    }

    #[Test]
    public function a_release_refreshes_the_customers_goldscore(): void
    {
        Event::fake([CustomerLedgerChanged::class]);

        $this->actingAs($this->user)->post(route('girvi.release.store', $this->loan), [
            'released_on' => '2026-03-15',
        ]);

        Event::assertDispatched(
            CustomerLedgerChanged::class,
            fn (CustomerLedgerChanged $event) => $event->reason === 'girvi.released',
        );
    }

    #[Test]
    public function the_girvi_receipt_prints_the_pledged_items(): void
    {
        $this->actingAs($this->user)
            ->get(route('girvi.loans.receipt', $this->loan))
            ->assertOk()
            ->assertSee($this->customer->full_name)
            ->assertSee('GRT-19/27-1')
            ->assertSee('Gold-Chain')
            ->assertSee('Customer Name')
            ->assertSee('Predicted Value')
            ->assertSee('Print Receipt')
            ->assertSee('we will not take any responsibility if the receipt is lost');
    }
}
