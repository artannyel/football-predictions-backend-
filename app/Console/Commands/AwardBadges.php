<?php

namespace App\Console\Commands;

use App\Jobs\AwardRankingBadges;
use Illuminate\Console\Command;

class AwardBadges extends Command
{
    protected $signature = 'badges:award {type : monthly or season} {period? : YYYY-MM or YYYY}';
    protected $description = 'Award ranking badges for a specific period.';

    public function handle()
    {
        $type = $this->argument('type');
        $period = $this->argument('period');

        if (!$period) {
            if ($type === 'monthly') {
                $period = now()->subMonth()->format('Y-m');
            } elseif ($type === 'season') {
                $period = now()->subYear()->format('Y');
            }
        }

        $this->info("Dispatching AwardRankingBadges for {$type} - {$period}...");

        AwardRankingBadges::dispatch($period, $type);

        $this->info("Job dispatched.");
    }
}
