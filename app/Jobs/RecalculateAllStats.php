<?php

namespace App\Jobs;

use App\Models\League;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateAllStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::channel('recalculation')->info('Dispatching recalculation jobs for all leagues...');

        League::chunk(100, function ($leagues) {
            foreach ($leagues as $league) {
                RecalculateLeagueStats::dispatch($league->id);
            }
        });

        Log::channel('recalculation')->info('All league recalculation jobs dispatched.');
    }
}
