<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\UserStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateGlobalStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 horas

    public function middleware(): array
    {
        // Evita que dois jobs de recálculo rodem ao mesmo tempo
        return [(new WithoutOverlapping('global_stats'))->releaseAfter(60)];
    }

    public function handle(UserStatsService $userStatsService): void
    {
        Log::channel('recalculation')->info("Starting FULL Global Stats Recalculation...");

        // 1. Limpar tabelas (Full Reset)
        DB::table('user_match_stats')->truncate();
        DB::table('user_stats')->truncate();

        Log::channel('recalculation')->info("Tables truncated. Processing matches...");

        // 2. Buscar jogos finalizados
        FootballMatch::where('status', 'FINISHED')
            ->orderBy('utc_date', 'asc')
            ->chunk(50, function ($matches) use ($userStatsService) {
                foreach ($matches as $match) {
                    $userIds = Prediction::where('match_id', $match->external_id)
                        ->distinct()
                        ->pluck('user_id');

                    foreach ($userIds as $userId) {
                        $userStatsService->processPrediction($userId, $match);
                    }
                }
                Log::channel('recalculation')->info("Processed chunk of matches.");
            });

        Log::channel('recalculation')->info("Global Stats Recalculation finished.");
    }
}
