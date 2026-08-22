<?php

namespace Tests\Feature\Girvi;

use App\Events\CustomerLedgerChanged;
use App\Models\Customer;
use App\Models\GoldLoan;
use App\Models\GoldLoanItem;
use App\Models\User;
use App\Services\CustomerSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GirviDepositTest extends TestCase
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
    public function the_entry_screen_offers_the_stores_customers(): void
    {
        $this->actingAs($this->user)
            ->get(route('girvi.loans.create'))
            ->assertOk()
            ->assertSee('Item detail')
            ->assertSee('Estimate in %')
            ->assertSee('Cash in hand')
            ->assertSee('Bank')
            ->assertSee($this->customer->full_name);
    }

    #[Test]
    public function the_picker_shows_the_stores_own_ledger_number(): void
    {
        $this->customer->stores()->updateExistingPivot($this->user->store_id, ['ledger_no' => '171']);

        $this->actingAs($this->user)
            ->get(route('girvi.loans.create'))
            ->assertOk()
            ->assertSee('[171]');
    }

    #[Test]
    public function two_stores_can_use_the_same_ledger_number(): void
    {
        $this->customer->stores()->updateExistingPivot($this->user->store_id, ['ledger_no' => '171']);

        $other = User::factory()->owner()->create();
        $another = Customer::factory()->create();
        $another->stores()->attach($other->store_id, ['first_seen_at' => now(), 'ledger_no' => '171']);

        $this->assertSame('171', $this->customer->ledgerNoFor($this->user->store_id));
        $this->assertSame('171', $another->ledgerNoFor($other->store_id));
    }

    #[Test]
    public function a_deposit_stores_the_pledge_with_the_weights_worked_out(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('girvi.loans.store'), $this->payload());

        $loan = GoldLoan::query()->firstOrFail();

        $response->assertRedirect(route('girvi.loans.receipt', $loan));

        $this->assertSame('10.000', $loan->net_weight_grams);
        $this->assertSame('9.160', $loan->fine_weight_grams);
        $this->assertSame('54960.00', $loan->total_value);
        $this->assertSame('41220.00', $loan->estimate_amount);
        $this->assertSame('40000.00', $loan->principal_amount);
        $this->assertSame('cash', $loan->paid_from);
        $this->assertSame('60.00', $loan->interest_rate);
        $this->assertSame(5.0, $loan->monthlyInterestRate());
        $this->assertMatchesRegularExpression('/^GRT-19\/27-\d{3,4}$/', $loan->receipt_no);
        $this->assertCount(1, $loan->items);
        $this->assertSame($this->user->id, $loan->created_by_user_id);

        $this->user->store->refresh();
        $this->assertSame('400000.00', $this->user->store->cash_in_hand);
        $this->assertSame('360000.00', $this->user->store->girvi_cash_in_hand);
        $this->assertSame('600000.00', $this->user->store->bank_balance);
        $this->assertSame('600000.00', $this->user->store->girvi_bank_balance);
        $this->assertSame('1000000.00', $this->user->store->opening_capital);
        $this->assertSame('1000000.00', $this->user->store->girvi_opening_capital);
    }

    #[Test]
    public function a_bank_disbursement_comes_out_of_the_girvi_bank(): void
    {
        $this->actingAs($this->user)
            ->post(route('girvi.loans.store'), $this->payload(['paid_from' => 'bank']))
            ->assertRedirect();

        $loan = GoldLoan::query()->firstOrFail();

        $this->assertSame('bank', $loan->paid_from);

        $this->user->store->refresh();
        $this->assertSame('400000.00', $this->user->store->girvi_cash_in_hand);
        $this->assertSame('560000.00', $this->user->store->girvi_bank_balance);

        $this->actingAs($this->user)
            ->get(route('girvi.loans.show', $loan))
            ->assertOk()
            ->assertSee('Paid from')
            ->assertSee('Bank');
    }

    #[Test]
    public function the_maturity_date_follows_the_duration(): void
    {
        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload([
            'disbursed_on' => '2026-01-10',
            'duration_months' => 9,
        ]));

        $this->assertSame('2026-10-10', GoldLoan::query()->value('due_on')->toDateString());
    }

    #[Test]
    public function the_pledge_page_and_the_dashboard_show_what_is_held(): void
    {
        $this->actingAs($this->user)
            ->post(route('girvi.loans.store'), $this->payload())
            ->assertRedirect();

        $loan = GoldLoan::query()->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('girvi.loans.index'))
            ->assertOk()
            ->assertSee('Receipt');

        $this->actingAs($this->user)
            ->get(route('girvi.loans.receipt', $loan))
            ->assertOk()
            ->assertSee('Print Receipt')
            ->assertSee($this->customer->full_name)
            ->assertSee('Gold-Chain');

        $this->actingAs($this->user)
            ->get(route('girvi.loans.show', $loan))
            ->assertOk()
            ->assertSee($this->customer->full_name)
            ->assertSee($loan->receipt_no)
            ->assertSee('Chain')
            ->assertSee('Print Receipt')
            ->assertSee('Collect interest');

        $this->actingAs($this->user)
            ->get(route('girvi.dashboard'))
            ->assertOk()
            ->assertSee('Money out')
            ->assertSee('₹40,000')
            ->assertSee('9.160 g');
    }

    #[Test]
    public function the_receipt_prints_the_customers_signature(): void
    {
        Storage::fake('local');
        app(CustomerSignature::class)->store(
            $this->customer,
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        );

        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload());
        $loan = GoldLoan::query()->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('girvi.loans.receipt', $loan))
            ->assertOk()
            ->assertSee('Customer sign')
            ->assertSee('gs-slip-sign-img', false)
            ->assertSee('data:image/png;base64,', false);
    }

    #[Test]
    public function gold_and_silver_are_counted_separately_in_what_is_held(): void
    {
        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload([
            'items' => [
                [
                    'metal_type' => 'gold', 'item_type' => 'Chain', 'quantity' => 1,
                    'gross_weight_grams' => 12.5, 'less_weight_grams' => 2.5,
                    'weight_percent' => 91.6, 'rate_per_gram' => 6000,
                ],
                [
                    'metal_type' => 'silver', 'item_type' => 'Anklet', 'quantity' => 2,
                    'gross_weight_grams' => 100, 'less_weight_grams' => 0,
                    'weight_percent' => 90, 'rate_per_gram' => 90,
                ],
            ],
            'principal_amount' => 40000,
        ]));

        $this->assertSame(
            ['gold' => 9.16, 'silver' => 90.0],
            GoldLoanItem::fineWeightHeld(),
        );

        $this->actingAs($this->user)
            ->get(route('girvi.dashboard'))
            ->assertOk()
            ->assertSee('Gold held')
            ->assertSee('9.160 g')
            ->assertSee('Silver held')
            ->assertSee('90.000 g');
    }

    #[Test]
    public function released_metal_stops_counting_as_held(): void
    {
        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload());

        $this->assertSame(9.16, GoldLoanItem::fineWeightHeld()['gold']);

        $this->actingAs($this->user)->post(
            route('girvi.release.store', GoldLoan::query()->firstOrFail()),
            ['released_on' => now()->toDateString()],
        );

        $this->assertSame(0.0, GoldLoanItem::fineWeightHeld()['gold']);
    }

    #[Test]
    public function a_loan_above_the_estimate_is_refused(): void
    {
        $this->actingAs($this->user)
            ->post(route('girvi.loans.store'), $this->payload(['principal_amount' => 45000]))
            ->assertSessionHasErrors('principal_amount');

        $this->assertSame(0, GoldLoan::query()->count());
    }

    #[Test]
    public function receipt_numbers_are_random_per_store(): void
    {
        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload());
        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload());

        $numbers = GoldLoan::query()->orderBy('id')->pluck('receipt_no');

        $this->assertCount(2, $numbers->unique());
        $numbers->each(fn (string $number) => $this->assertMatchesRegularExpression('/^GRT-19\/27-\d{3,4}$/', $number));

        $other = User::factory()->owner()->create();
        $shared = Customer::factory()->create();
        $shared->stores()->attach($other->store_id, ['first_seen_at' => now()]);

        $this->actingAs($other)->post(route('girvi.loans.store'), $this->payload([
            'customer_id' => $shared->id,
        ]));

        $this->assertMatchesRegularExpression(
            '/^GRT-19\/27-\d{3,4}$/',
            GoldLoan::withoutGlobalScopes()->where('store_id', $other->store_id)->value('receipt_no'),
        );
    }

    #[Test]
    public function a_deposit_refreshes_the_customers_goldscore(): void
    {
        Event::fake([CustomerLedgerChanged::class]);

        $this->actingAs($this->user)->post(route('girvi.loans.store'), $this->payload());

        Event::assertDispatched(
            CustomerLedgerChanged::class,
            fn (CustomerLedgerChanged $event) => $event->customer->is($this->customer)
                && $event->reason === 'girvi.deposited',
        );
    }

    #[Test]
    public function staff_cannot_take_a_pledge_in(): void
    {
        $staff = User::factory()->staff()->create(['store_id' => $this->user->store_id]);

        $this->actingAs($staff)
            ->post(route('girvi.loans.store'), $this->payload())
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'invoice_no' => '110489',
            'disbursed_on' => now()->toDateString(),
            'duration_months' => 6,
            'loan_reason' => 'Transaction Loan',
            'loan_type' => 'Ornaments',
            'rate_per_gram' => 6000,
            'estimate_percent' => 75,
            'interest_rate' => 5,
            'principal_amount' => 40000,
            'paid_from' => 'cash',
            'items' => [
                [
                    'metal_type' => 'gold',
                    'item_type' => 'Chain',
                    'quantity' => 1,
                    'gross_weight_grams' => 12.5,
                    'less_weight_grams' => 2.5,
                    'weight_percent' => 91.6,
                    'rate_per_gram' => 6000,
                ],
            ],
        ], $overrides);
    }
}
