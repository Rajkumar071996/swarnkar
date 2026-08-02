<?php

namespace App\Services\Scoring;

use App\Models\Customer;
use Illuminate\Support\Carbon;

interface ComponentCalculator
{
    public function key(): string;

    public function calculate(Customer $customer, Carbon $asOf): ScoreComponent;
}
