<?php

namespace App\Actions;

use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

class GetMatchPredictionStatsAction
{
    public function execute(int $matchExternalId): array
    {
        $stats = Prediction::where('match_id', $matchExternalId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN home_score > away_score THEN 1 ELSE 0 END) as home_wins,
                SUM(CASE WHEN away_score > home_score THEN 1 ELSE 0 END) as away_wins,
                SUM(CASE WHEN home_score = away_score THEN 1 ELSE 0 END) as draws
            ')
            ->first();

        if (!$stats || $stats->total == 0) {
            return [
                'total' => 0,
                'home_win_percentage' => 0,
                'away_win_percentage' => 0,
                'draw_percentage' => 0,
            ];
        }

        return [
            'total' => (int) $stats->total,
            'home_win_percentage' => round(($stats->home_wins / $stats->total) * 100),
            'away_win_percentage' => round(($stats->away_wins / $stats->total) * 100),
            'draw_percentage' => round(($stats->draws / $stats->total) * 100),
        ];
    }
}
