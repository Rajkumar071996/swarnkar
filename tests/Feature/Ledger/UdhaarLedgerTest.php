<?php

namespace Tests\Feature\Ledger;

use App\Enums\UdhaarStatus;
use App\Models\Customer;
use App\Models\ScoreSnapshot;
use App\Models\Udhaar;
use App\Models\User;
use App\Services\UdhaarLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UdhaarLedgerTest extends TestCase
{
    use RefreshDatabase;

    private UdhaarLedger $ledger;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(UdhaarLedger::class);
        $this->user = User::factory()->owner()->create();
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function issuing_credit_stamps_the_store_and_the_person_who_approved_it(): void
    {
        $udhaar = $this->issue(50000);

        $this->assertSame($this->user->store_id, $udhaar->store_id);
        $this->assertSame($this->user->id, $udhaar->created_by_user_id);
        $this->assertSame(UdhaarStatus::Open, $udhaar->status);
        $this->assertSame(50000.0, $udhaar->outstandingAmount());
    }

    #[Test]
    public function a_part_payment_leaves_the_balance_outstanding(): void
    {
        $udhaar = $this->issue(50000);

        $this->record($udhaar, 20000);

        $udhaar->refresh();
        $this->assertSame(UdhaarStatus::PartiallyPaid, $udhaar->status);
        $this->assertSame(30000.0, $udhaar->outstandingAmount());
        $this->assertNull($udhaar->settled_on);
    }

    #[Test]
    public function part_payments_that_add_up_settle_the_account(): void
    {
        $udhaar = $this->issue(50000);

        $this->record($udhaar, 20000, Carbon::today()->subDays(20));
        $this->record($udhaar, 30000, Carbon::today()->subDays(5));

        $udhaar->refresh();
        $this->assertSame(UdhaarStatus::Settled, $udhaar->status);
        $this->assertSame(0.0, $udhaar->outstandingAmount());
        $this->assertTrue($udhaar->settled_on->isSameDay(Carbon::today()->subDays(5)));
    }

    #[Test]
    public function a_received_entry_can_clear_the_oldest_bills_first(): void
    {
        $older = $this->issue(20000, Carbon::today()->subMonths(2));
        $newer = $this->issue(30000, Carbon::today()->subMonth());

        $result = $this->ledger->receive(
            $this->customer,
            25000,
            Carbon::today(),
            'cash',
            null,
            $this->user,
        );

        $this->assertCount(2, $result['payments']);
        $this->assertSame(0.0, $result['advance_credited']);
        $this->assertSame(0.0, $older->fresh()->outstandingAmount());
        $this->assertSame(UdhaarStatus::Settled, $older->fresh()->status);
        $this->assertSame(25000.0, $newer->fresh()->outstandingAmount());
        $this->assertSame(UdhaarStatus::PartiallyPaid, $newer->fresh()->status);
    }

    #[Test]
    public function a_received_entry_can_target_one_bill(): void
    {
        $untouched = $this->issue(20000, Carbon::today()->subMonths(2));
        $target = $this->issue(30000, Carbon::today()->subMonth());

        $this->ledger->receive(
            $this->customer,
            10000,
            Carbon::today(),
            'upi',
            'UPI123',
            $this->user,
            $target->id,
        );

        $this->assertSame(20000.0, $untouched->fresh()->outstandingAmount());
        $this->assertSame(20000.0, $target->fresh()->outstandingAmount());
    }

    #[Test]
    public function a_payment_larger_than_the_balance_is_refused(): void
    {
        $udhaar = $this->issue(50000);

        $this->expectException(ValidationException::class);
        $this->record($udhaar, 50001);
    }

    #[Test]
    public function overdue_accounts_report_how_many_days_they_have_run(): void
    {
        $udhaar = Udhaar::factory()
            ->issuedOn(Carbon::today()->subDays(75))
            ->create(['store_id' => $this->user->store_id, 'customer_id' => $this->customer->id]);

        // Factory default terms are 30 days, so 75 days on gives 45 days past due.
        $this->assertSame(45, $udhaar->daysOverdue());
    }

    #[Test]
    public function a_settled_account_stops_counting_days_at_the_settlement_date(): void
    {
        $udhaar = $this->issue(10000, Carbon::today()->subDays(90));
        $this->record($udhaar, 10000, Carbon::today()->subDays(50));

        // Due 60 days ago, paid 50 days ago: ten days late, and it stays ten.
        $this->assertSame(10, $udhaar->fresh()->daysOverdue());
    }

    #[Test]
    public function issuing_an_account_that_is_already_long_overdue_records_it_as_defaulted(): void
    {
        // Back-dated entries happen while a store is catching up on its books.
        $udhaar = $this->issue(10000, Carbon::today()->subDays(120));

        $this->assertSame(UdhaarStatus::Defaulted, $udhaar->status);
    }

    #[Test]
    public function aging_rolls_long_overdue_accounts_into_default(): void
    {
        $stillFresh = $this->openUdhaarDue(Carbon::today()->subDays(10));
        $longGone = $this->openUdhaarDue(Carbon::today()->subDays(90));

        $this->assertSame(1, $this->ledger->ageOpenAccounts());

        $this->assertSame(UdhaarStatus::Open, $stillFresh->fresh()->status);
        $this->assertSame(UdhaarStatus::Defaulted, $longGone->fresh()->status);
    }

    #[Test]
    public function aging_leaves_settled_accounts_alone(): void
    {
        $udhaar = $this->issue(10000, Carbon::today()->subDays(120));
        $this->record($udhaar, 10000, Carbon::today()->subDays(115));

        $this->ledger->ageOpenAccounts();

        $this->assertSame(UdhaarStatus::Settled, $udhaar->fresh()->status);
    }

    #[Test]
    public function a_write_off_records_who_made_the_call(): void
    {
        $udhaar = $this->issue(50000);

        $this->ledger->writeOff($udhaar, $this->user, 'Customer untraceable.');

        $udhaar->refresh();
        $this->assertSame(UdhaarStatus::WrittenOff, $udhaar->status);
        $this->assertStringContainsString($this->user->name, $udhaar->notes);
        $this->assertStringContainsString('Customer untraceable.', $udhaar->notes);
    }

    #[Test]
    public function a_written_off_account_is_not_quietly_reopened_by_aging(): void
    {
        $udhaar = $this->issue(50000, Carbon::today()->subDays(200));
        $this->ledger->writeOff($udhaar, $this->user);

        $this->ledger->ageOpenAccounts();
        $udhaar->refresh();
        $udhaar->syncStatus();

        $this->assertSame(UdhaarStatus::WrittenOff, $udhaar->status);
    }

    #[Test]
    public function ledger_movement_refreshes_the_customers_score(): void
    {
        $this->assertSame(0, ScoreSnapshot::where('customer_id', $this->customer->id)->count());

        $this->issue(50000);

        $this->assertSame(1, ScoreSnapshot::where('customer_id', $this->customer->id)->count());
    }

    private function issue(float $amount, ?Carbon $issuedOn = null): Udhaar
    {
        $issuedOn = $issuedOn ?? Carbon::today();

        return $this->ledger->issue([
            'customer_id' => $this->customer->id,
            'item_description' => 'Gold chain, 22k',
            'principal_amount' => $amount,
            'issued_on' => $issuedOn,
            'due_on' => $issuedOn->copy()->addDays(30),
        ], $this->user);
    }

    /**
     * Built through the factory rather than the ledger so the row stays Open
     * past its due date, which is precisely the state aging exists to clean up.
     */
    private function openUdhaarDue(Carbon $dueOn): Udhaar
    {
        return Udhaar::factory()
            ->issuedOn($dueOn->copy()->subDays(30))
            ->create([
                'store_id' => $this->user->store_id,
                'customer_id' => $this->customer->id,
                'principal_amount' => 10000,
            ]);
    }

    private function record(Udhaar $udhaar, float $amount, ?Carbon $paidOn = null): void
    {
        $this->ledger->recordPayment(
            $udhaar,
            $amount,
            $paidOn ?? Carbon::today(),
            'cash',
            null,
            $this->user
        );
    }
}
