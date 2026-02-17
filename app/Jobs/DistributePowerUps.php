<?php

namespace App\Jobs;

use App\Models\League;
use App\Services\PowerUpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistributePowerUps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PowerUpService $powerUpService): void
    {
        Log::channel('recalculation')->info("Starting Power-Up distribution for active leagues...");

        League::where('is_active', true)->chunk(5, function ($leagues) use ($powerUpService) {
            foreach ($leagues as $league) {
                $balance = $powerUpService->calculateInitialBalance($league->competition_id);

                DB::table('league_user')
                    ->where('league_id', $league->id)
                    ->where('initial_powerups', 0)
                    ->update(['initial_powerups' => $balance]);

                Log::channel('recalculation')->info("League {$league->id}: Distributed {$balance} initial powerups to members.");
            }
        });

        Log::channel('recalculation')->info("Power-Up distribution finished.");
    }
}
