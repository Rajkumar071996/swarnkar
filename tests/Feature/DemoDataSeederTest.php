<?php

namespace Tests\Feature;

use App\Enums\RiskBand;
use App\Models\Customer;
use App\Models\ScoreSnapshot;
use App\Models\Store;
use App\Models\Udhaar;
use App\Models\User;
use App\Services\CreditExposure;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The demo data is what anyone new to the project sees first, so its shape is
 * worth pinning: a tweak to the engine that flattens every customer into one
 * band should fail here rather than be discovered during a walkthrough.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoDataSeeder::class);
    }

    #[Test]
    public function it_creates_two_stores_so_cross_store_scoring_is_visible(): void
    {
        $this->assertSame(2, Store::count());
        $this->assertSame(4, User::count());
    }

    #[Test]
    public function the_demo_sign_in_accounts_work(): void
    {
        foreach (['owner@swarnkar.test', 'staff@swarnkar.test', 'karigar@swarnkar.test'] as $email) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));

            $this->post(route('logout'));
        }
    }

    #[Test]
    public function every_band_is_represented_in_meaningful_numbers(): void
    {
        $bands = ScoreSnapshot::all()->countBy(fn (ScoreSnapshot $s) => $s->band->value);

        // Three apiece rather than one: a single straggler in a band usually
        // means the engine has drifted and the demo book is quietly collapsing
        // towards one end of the range.
        foreach ([RiskBand::Green, RiskBand::Yellow, RiskBand::Red, RiskBand::Unscored] as $band) {
            $this->assertGreaterThanOrEqual(
                3,
                $bands[$band->value] ?? 0,
                "The demo data produced too few {$band->value} customers."
            );
        }
    }

    #[Test]
    public function rajesh_kumar_scores_the_785_from_the_product_mock(): void
    {
        $rajesh = Customer::all()->firstWhere('full_name', 'Rajesh Kumar');
        $snapshot = ScoreSnapshot::where('customer_id', $rajesh->id)->first();

        // His history is seeded relative to today, so the recency weighting
        // drifts by a point or two as the calendar moves. The band and the
        // rough position are the parts that matter.
        $this->assertEqualsWithDelta(785, $snapshot->score, 10);
        $this->assertSame(RiskBand::Green, $snapshot->band);
    }

    #[Test]
    public function rajesh_kumars_report_rests_entirely_on_his_credit_record(): void
    {
        $rajesh = Customer::all()->firstWhere('full_name', 'Rajesh Kumar');
        $components = collect(ScoreSnapshot::where('customer_id', $rajesh->id)->first()->breakdown['components'])
            ->keyBy('key');

        $this->assertGreaterThan(0, $components['udhaar']['observations']);

        // No pledged loan and no flags, so those carry no weight at all rather
        // than dragging him down as zeros.
        $this->assertNull($components['gold_loan']['ratio']);
        $this->assertSame(0, $components['gold_loan']['effective_weight']);
        $this->assertNull($components['flags']['ratio']);

        $this->assertEqualsWithDelta(100, $components['udhaar']['effective_weight'], 0.5);
    }

    #[Test]
    public function suresh_agarwal_demonstrates_credit_hidden_at_another_jeweller(): void
    {
        $suresh = Customer::all()->firstWhere('full_name', 'Suresh Agarwal');
        $snapshot = ScoreSnapshot::where('customer_id', $suresh->id)->first();
        $home = Store::where('name', 'Swarnkar Jewellers')->firstOrFail();

        // Nothing in his record with Swarnkar suggests a problem.
        $this->assertSame(RiskBand::Green, $snapshot->band);
        $this->assertSame(0.0, (float) Udhaar::query()->networkWide()
            ->where('customer_id', $suresh->id)
            ->where('store_id', $home->id)
            ->outstanding()
            ->sum('principal_amount'));

        // But he is carrying 50,000 at the other store, which is exactly what
        // the exposure check is there to reveal.
        $exposure = app(CreditExposure::class)->for($suresh, $home->id);

        $this->assertSame(50000.0, $exposure->elsewhere);
        $this->assertSame(0.0, $exposure->ownStore);
        $this->assertTrue($exposure->hasHiddenExposure());
        // Not yet overdue, so the score cannot see it at all.
        $this->assertFalse($exposure->hasOverdue());
    }

    #[Test]
    public function a_customer_with_no_history_is_unscored_rather_than_scored_at_the_floor(): void
    {
        $neha = Customer::all()->firstWhere('full_name', 'Neha Saxena');
        $snapshot = ScoreSnapshot::where('customer_id', $neha->id)->first();

        $this->assertNull($snapshot->score);
        $this->assertSame(RiskBand::Unscored, $snapshot->band);
    }

    #[Test]
    public function scores_are_derived_from_the_ledger_rather_than_written_in(): void
    {
        // Every snapshot should be reproducible by recomputing from the ledger.
        $before = ScoreSnapshot::pluck('score', 'customer_id');

        $this->artisan('goldscore:recompute')->assertSuccessful();

        $after = ScoreSnapshot::query()
            ->orderByDesc('id')
            ->get()
            ->unique('customer_id')
            ->pluck('score', 'customer_id');

        foreach ($before as $customerId => $score) {
            $this->assertSame($score, $after[$customerId], "Customer {$customerId} did not recompute to the same score.");
        }
    }

    #[Test]
    public function every_seeded_customer_has_a_snapshot(): void
    {
        $this->assertSame(Customer::count(), ScoreSnapshot::distinct('customer_id')->count('customer_id'));
    }
}
