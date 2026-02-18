<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\UserStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateGlobalStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 horas (pode demorar)

    public function handle(UserStatsService $userStatsService): void
    {
        Log::channel('recalculation')->info("Starting FULL Global Stats Recalculation...");

        // 1. Limpar tabelas (Full Reset)
        DB::table('user_match_stats')->truncate();
        DB::table('user_stats')->truncate();

        Log::channel('recalculation')->info("Tables truncated. Processing matches...");

        // 2. Buscar jogos finalizados
        // Processar em chunks para não estourar memória
        FootballMatch::where('status', 'FINISHED')
            ->orderBy('utc_date', 'asc')
            ->chunk(50, function ($matches) use ($userStatsService) {
                foreach ($matches as $match) {
                    // Busca palpites deste jogo
                    // Precisamos iterar sobre os palpites para aplicar a lógica de "Melhor Palpite"
                    // O Service espera (userId, match).
                    // Então pegamos os userIds distintos que palpitaram neste jogo.

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
