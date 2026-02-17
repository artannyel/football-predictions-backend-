<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\League;

class PowerUpService
{
    /**
     * Calcula a quantidade inicial de Power-Ups baseada nos jogos restantes.
     */
    public function calculateInitialBalance(int $competitionId): int
    {
        $remainingMatches = FootballMatch::where('competition_id', $competitionId)
            ->whereIn('status', ['SCHEDULED', 'TIMED'])
            ->where('utc_date', '>', now())
            ->count();

        // Regra: 5% dos jogos restantes
        $balance = (int) round($remainingMatches * 0.05);

        // Mínimo 1, Máximo 10 (para não exagerar em ligas longas)
        return max(1, min($balance, 10));
    }
}
