<?php

namespace Tests\Unit\Girvi;

use App\Services\Girvi\GoldValuation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GoldValuationTest extends TestCase
{
    private GoldValuation $valuation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->valuation = new GoldValuation;
    }

    #[Test]
    public function net_weight_is_gross_less_the_stones(): void
    {
        $this->assertSame(10.0, $this->valuation->netWeight(12.5, 2.5));
    }

    #[Test]
    public function fine_weight_follows_the_weight_percentage_rather_than_karat(): void
    {
        $this->assertSame(9.16, $this->valuation->fineWeight(10, 91.6));
    }

    #[Test]
    public function it_prices_a_pledge_and_totals_the_rows(): void
    {
        $priced = $this->valuation->priceItems([
            [
                'item_type' => 'Chain', 'quantity' => 1,
                'gross_weight_grams' => 12.5, 'less_weight_grams' => 2.5,
                'weight_percent' => 91.6, 'rate_per_gram' => 6000,
            ],
            [
                'item_type' => 'Ring', 'quantity' => 2,
                'gross_weight_grams' => 5, 'less_weight_grams' => 0,
                'weight_percent' => 75, 'rate_per_gram' => 6000,
            ],
        ]);

        $this->assertSame(10.0, $priced['items'][0]['net_weight_grams']);
        $this->assertSame(9.16, $priced['items'][0]['fine_weight_grams']);
        $this->assertSame(54960.0, $priced['items'][0]['total_amount']);

        $this->assertSame(3.75, $priced['items'][1]['fine_weight_grams']);
        $this->assertSame(22500.0, $priced['items'][1]['total_amount']);

        $this->assertSame(17.5, $priced['gross_weight_grams']);
        $this->assertSame(15.0, $priced['net_weight_grams']);
        $this->assertSame(12.91, $priced['fine_weight_grams']);
        $this->assertSame(77460.0, $priced['total_value']);
    }

    #[Test]
    public function the_estimate_is_the_share_of_value_the_shop_will_lend(): void
    {
        $this->assertSame(58095.0, $this->valuation->estimateAmount(77460, 75));
    }
}
