<?php

namespace Tests\Feature\Scoring;

use App\Enums\DefaultFlagReason;
use App\Enums\DefaultFlagStatus;
use App\Enums\RiskBand;
use App\Models\Customer;
use App\Models\DefaultFlag;
use App\Models\GoldLoan;
use App\Models\Store;
use App\Models\Udhaar;
use App\Services\Scoring\GoldScoreEngine;
use App\Services\Scoring\ScoreComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoldScoreEngineTest extends TestCase
{
    use RefreshDatabase;

    private GoldScoreEngine $engine;

    private Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(GoldScoreEngine::class);
        $this->asOf = Carbon::parse('2026-08-01');
    }

    #[Test]
    public function a_customer_with_no_history_is_unscored_rather_than_scored_300(): void
    {
        $result = $this->engine->score(Customer::factory()->create(), $this->asOf);

        $this->assertNull($result->score);
        $this->assertSame(RiskBand::Unscored, $result->band);
        $this->assertSame(0.0, $result->recommendedCreditLimit);
    }

    #[Test]
    public function a_clean_run_of_cleared_credit_scores_in_the_green_band(): void
    {
        $customer = Customer::factory()->create();
        $this->clearedCredit($customer, 3, 100000);
        $this->clearedCredit($customer, 9, 60000);

        $result = $this->engine->score($customer, $this->asOf);

        $this->assertSame(900, $result->score);
        $this->assertSame(RiskBand::Green, $result->band);
    }

    #[Test]
    public function unpaid_credit_drags_the_score_into_the_red_band(): void
    {
        $customer = Customer::factory()->create();

        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(200), 30)
            ->create(['customer_id' => $customer->id, 'principal_amount' => 80000]);
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(120), 30)
            ->writtenOff()
            ->create(['customer_id' => $customer->id, 'principal_amount' => 50000]);

        $result = $this->engine->score($customer, $this->asOf);

        $this->assertSame(RiskBand::Red, $result->band);
        $this->assertLessThan(650, $result->score);
    }

    #[Test]
    public function weights_are_renormalised_across_the_components_that_have_data(): void
    {
        $customer = Customer::factory()->create();
        $this->clearedCredit($customer, 3, 100000);

        $result = $this->engine->score($customer, $this->asOf);
        $udhaar = $this->componentOf($result, 'udhaar');
        $goldLoan = $this->componentOf($result, 'gold_loan');

        $this->assertNull($goldLoan->ratio, 'A customer with no gold loan should have no gold loan ratio.');

        // Credit is the only component with data, so it must carry the full 100%
        // rather than being capped at its nominal 60% and dragging the score down.
        $breakdown = collect($result->breakdown()['components'])->firstWhere('key', 'udhaar');
        $this->assertSame(100.0, $breakdown['effective_weight']);
        $this->assertSame(900, $result->score);
        $this->assertSame(60.0, $udhaar->weight);
    }

    #[Test]
    public function udhaar_settled_late_scores_lower_than_udhaar_settled_on_time(): void
    {
        $prompt = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subMonths(3))
            ->settledOnTime()
            ->create(['customer_id' => $prompt->id, 'principal_amount' => 100000]);

        $slow = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subMonths(3))
            ->settledLate(45)
            ->create(['customer_id' => $slow->id, 'principal_amount' => 100000]);

        $this->assertGreaterThan(
            $this->engine->score($slow, $this->asOf)->score,
            $this->engine->score($prompt, $this->asOf)->score,
        );
    }

    #[Test]
    public function an_open_udhaar_that_is_not_yet_due_carries_no_signal(): void
    {
        $customer = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(5))
            ->create(['customer_id' => $customer->id]);

        $udhaar = $this->componentOf($this->engine->score($customer, $this->asOf), 'udhaar');

        $this->assertNull($udhaar->ratio);
    }

    #[Test]
    public function an_udhaar_more_than_sixty_days_overdue_scores_zero(): void
    {
        $customer = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(120))
            ->create(['customer_id' => $customer->id]);

        $udhaar = $this->componentOf($this->engine->score($customer, $this->asOf), 'udhaar');

        $this->assertSame(0.0, $udhaar->ratio);
    }

    #[Test]
    public function larger_accounts_weigh_more_than_small_ones(): void
    {
        $customer = Customer::factory()->create();

        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subMonths(2))
            ->settledOnTime()
            ->create(['customer_id' => $customer->id, 'principal_amount' => 2000]);

        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(120), 30)
            ->create(['customer_id' => $customer->id, 'principal_amount' => 500000]);

        $udhaar = $this->componentOf($this->engine->score($customer, $this->asOf), 'udhaar');

        // Two observations, one perfect and one zero, but the zero is 250x
        // larger, so the ratio must sit far below the unweighted 0.5.
        $this->assertLessThan(0.1, $udhaar->ratio);
    }

    #[Test]
    public function only_verified_default_flags_affect_the_score(): void
    {
        $customer = Customer::factory()->create();
        $this->clearedCredit($customer, 3, 100000);

        DefaultFlag::factory()->reason(DefaultFlagReason::BouncedCheque)->create([
            'customer_id' => $customer->id,
            'occurred_on' => $this->asOf->copy()->subMonths(2),
        ]);

        $unaffected = $this->engine->score($customer, $this->asOf);
        $this->assertNull($this->componentOf($unaffected, 'flags')->ratio);
        $this->assertSame(900, $unaffected->score);

        DefaultFlag::query()->update([
            'status' => DefaultFlagStatus::Verified->value,
            'verified_at' => now(),
        ]);

        $affected = $this->engine->score($customer->fresh(), $this->asOf);
        $this->assertNotNull($this->componentOf($affected, 'flags')->ratio);
        $this->assertLessThan(900, $affected->score);
    }

    #[Test]
    public function a_clean_record_earns_no_free_credit_from_the_flags_component(): void
    {
        $customer = Customer::factory()->create();

        $flags = $this->componentOf($this->engine->score($customer, $this->asOf), 'flags');

        $this->assertNull($flags->ratio, 'Absence of flags must not be scored as positive evidence.');
    }

    #[Test]
    public function an_auctioned_pledge_is_the_worst_gold_loan_outcome(): void
    {
        $customer = Customer::factory()->create();
        GoldLoan::factory()->auctioned()->create([
            'customer_id' => $customer->id,
            'disbursed_on' => $this->asOf->copy()->subMonths(10),
            'due_on' => $this->asOf->copy()->subMonths(4),
        ]);

        $goldLoan = $this->componentOf($this->engine->score($customer, $this->asOf), 'gold_loan');

        $this->assertSame(0.0, $goldLoan->ratio);
    }

    #[Test]
    public function recent_behaviour_outweighs_old_behaviour(): void
    {
        // Clean two and a half years ago, then defaulted four months ago.
        $recentDefaulter = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subMonths(30))
            ->settledOnTime()
            ->create(['customer_id' => $recentDefaulter->id, 'principal_amount' => 50000]);
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(120), 30)
            ->create(['customer_id' => $recentDefaulter->id, 'principal_amount' => 50000]);

        // The mirror image: bad back then, clean recently.
        $reformed = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subMonths(30))
            ->settledLate(120)
            ->create(['customer_id' => $reformed->id, 'principal_amount' => 50000]);
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(120))
            ->settledOnTime()
            ->create(['customer_id' => $reformed->id, 'principal_amount' => 50000]);

        $this->assertGreaterThan(
            $this->engine->score($recentDefaulter, $this->asOf)->score,
            $this->engine->score($reformed, $this->asOf)->score,
        );
    }

    #[Test]
    public function scoring_reads_across_every_store_on_the_network(): void
    {
        $customer = Customer::factory()->create();
        $otherStore = Store::factory()->create();

        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(150))
            ->create([
                'store_id' => $otherStore->id,
                'customer_id' => $customer->id,
                'principal_amount' => 200000,
            ]);

        $result = $this->engine->score($customer, $this->asOf);

        $this->assertNotNull($result->score);
        $this->assertSame(1, $this->componentOf($result, 'udhaar')->observations);
    }

    #[Test]
    public function the_recommended_credit_limit_never_exceeds_the_configured_ceiling(): void
    {
        $customer = Customer::factory()->create();
        $this->clearedCredit($customer, 3, 9000000);

        $result = $this->engine->score($customer, $this->asOf);

        $this->assertSame(RiskBand::Green, $result->band);
        $this->assertSame(
            (float) config('goldscore.credit_limit.ceiling'),
            $result->recommendedCreditLimit
        );
    }

    #[Test]
    public function a_red_band_customer_is_recommended_no_store_credit(): void
    {
        $customer = Customer::factory()->create();
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(200), 30)
            ->writtenOff()
            ->create(['customer_id' => $customer->id, 'principal_amount' => 80000]);

        $result = $this->engine->score($customer, $this->asOf);

        $this->assertSame(RiskBand::Red, $result->band);
        $this->assertSame(0.0, $result->recommendedCreditLimit);
    }

    #[Test]
    public function credit_already_outstanding_elsewhere_is_deducted_from_the_recommendation(): void
    {
        $clean = Customer::factory()->create();
        $this->clearedCredit($clean, 3, 200000);

        $exposed = Customer::factory()->create();
        $this->clearedCredit($exposed, 3, 200000);
        // Not yet due, so it carries no scoring signal at all: the only way it
        // can affect the recommendation is through the exposure deduction.
        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(5), 30)
            ->create([
                'store_id' => Store::factory(),
                'customer_id' => $exposed->id,
                'principal_amount' => 150000,
            ]);

        $cleanResult = $this->engine->score($clean, $this->asOf);
        $exposedResult = $this->engine->score($exposed, $this->asOf);

        $this->assertSame($cleanResult->score, $exposedResult->score, 'The score itself should be untouched.');
        $this->assertSame(300000.0, $cleanResult->recommendedCreditLimit);
        $this->assertSame(150000.0, $exposedResult->recommendedCreditLimit);
    }

    #[Test]
    public function a_customer_already_at_their_ceiling_is_offered_nothing_further(): void
    {
        $customer = Customer::factory()->create();
        $this->clearedCredit($customer, 3, 100000);

        Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subDays(5), 30)
            ->create([
                'store_id' => Store::factory(),
                'customer_id' => $customer->id,
                'principal_amount' => 400000,
            ]);

        $result = $this->engine->score($customer, $this->asOf);

        $this->assertSame(RiskBand::Green, $result->band);
        $this->assertSame(0.0, $result->recommendedCreditLimit);
    }

    private function clearedCredit(Customer $customer, int $monthsAgo, float $amount): Udhaar
    {
        return Udhaar::factory()
            ->issuedOn($this->asOf->copy()->subMonthsNoOverflow($monthsAgo))
            ->settledOnTime()
            ->create(['customer_id' => $customer->id, 'principal_amount' => $amount]);
    }

    private function componentOf($result, string $key): ScoreComponent
    {
        foreach ($result->components as $component) {
            if ($component->key === $key) {
                return $component;
            }
        }

        $this->fail("No [{$key}] component in the score result.");
    }
}
