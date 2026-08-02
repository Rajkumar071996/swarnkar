<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Scoring\ScoreService;
use Illuminate\Console\Command;

class RecomputeGoldScores extends Command
{
    protected $signature = 'goldscore:recompute
                            {--customer=* : Limit to specific customer IDs}
                            {--chunk=200 : Customers to process per batch}';

    protected $description = 'Rebuild GoldScore snapshots from the current ledger data';

    public function handle(ScoreService $scores): int
    {
        $ids = $this->option('customer');

        $query = Customer::query()->when($ids, fn ($q) => $q->whereIn('id', $ids));
        $total = $query->count();

        if ($total === 0) {
            $this->components->warn('No customers matched.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById((int) $this->option('chunk'), function ($customers) use ($scores, $bar) {
            foreach ($customers as $customer) {
                $scores->refresh($customer);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Recomputed {$total} GoldScore snapshot(s).");

        return self::SUCCESS;
    }
}
