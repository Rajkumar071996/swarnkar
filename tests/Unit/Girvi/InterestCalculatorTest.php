<?php

namespace Tests\Unit\Girvi;

use App\Services\Girvi\InterestCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InterestCalculatorTest extends TestCase
{
    private InterestCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new InterestCalculator;
    }

    #[Test]
    public function a_part_month_is_charged_as_a_full_month(): void
    {
        $from = Carbon::parse('2026-01-01');

        $this->assertSame(1, $this->calculator->chargeableMonths($from, Carbon::parse('2026-02-01')));
        $this->assertSame(2, $this->calculator->chargeableMonths($from, Carbon::parse('2026-02-02')));
        $this->assertSame(3, $this->calculator->chargeableMonths($from, Carbon::parse('2026-03-15')));
    }

    #[Test]
    public function a_pledge_released_the_same_day_still_carries_one_month(): void
    {
        $day = Carbon::parse('2026-01-01');

        $this->assertSame(1, $this->calculator->chargeableMonths($day, $day));
    }

    #[Test]
    public function interest_is_simple_and_quoted_per_year(): void
    {
        $interest = $this->calculator->interest(
            40000,
            60,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertSame(6000.0, $interest);
    }
}
