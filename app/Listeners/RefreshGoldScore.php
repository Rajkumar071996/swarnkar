<?php

namespace App\Listeners;

use App\Events\CustomerLedgerChanged;
use App\Services\Scoring\ScoreService;

/**
 * Runs inline rather than on a queue: a jeweller who just recorded a payment
 * expects the score to reflect it before the customer leaves the counter, and
 * a single recompute is a handful of indexed queries.
 */
class RefreshGoldScore
{
    public function __construct(private readonly ScoreService $scores) {}

    public function handle(CustomerLedgerChanged $event): void
    {
        $this->scores->refresh($event->customer);
    }
}
