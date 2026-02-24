<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

class GetMatchPredictionStatsAction
{
    public function execute(int $matchExternalId): array
    {
        // 1. Estatísticas de Palpites
        $stats = Prediction::where('match_id', $matchExternalId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN home_score > away_score THEN 1 ELSE 0 END) as home_wins,
                SUM(CASE WHEN away_score > home_score THEN 1 ELSE 0 END) as away_wins,
                SUM(CASE WHEN home_score = away_score THEN 1 ELSE 0 END) as draws
            ')
            ->first();

        $result = [
            'total' => 0,
            'home_win_percentage' => 0,
            'away_win_percentage' => 0,
            'draw_percentage' => 0,
        ];

        if ($stats && $stats->total > 0) {
            $result = [
                'total' => (int) $stats->total,
                'home_win_percentage' => round(($stats->home_wins / $stats->total) * 100),
                'away_win_percentage' => round(($stats->away_wins / $stats->total) * 100),
                'draw_percentage' => round(($stats->draws / $stats->total) * 100),
            ];
        }

        // 2. Histórico Recente (Form Guide)
        $match = FootballMatch::where('external_id', $matchExternalId)->first();
        $form = [
            'home' => [],
            'away' => [],
        ];

        if ($match) {
            if ($match->home_team_id) {
                $form['home'] = $this->getTeamForm($match->home_team_id, $match->utc_date);
            }
            if ($match->away_team_id) {
                $form['away'] = $this->getTeamForm($match->away_team_id, $match->utc_date);
            }
        }

        $result['form'] = $form;

        return $result;
    }

    private function getTeamForm(int $teamId, $beforeDate): array
    {
        $matches = FootballMatch::where('status', 'FINISHED')
            ->where('utc_date', '<', $beforeDate)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            })
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('utc_date', 'desc')
            ->limit(5)
            ->get();

        return $matches->map(function ($match) use ($teamId) {
            $isHome = $match->home_team_id === $teamId;

            $myScore = $isHome ? $match->score_fulltime_home : $match->score_fulltime_away;
            $opponentScore = $isHome ? $match->score_fulltime_away : $match->score_fulltime_home;

            $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
            $opponentName = $opponent->short_name ?? $opponent->name ?? 'Unknown';

            $result = 'D';
            if ($myScore > $opponentScore) {
                $result = 'W';
            } elseif ($myScore < $opponentScore) {
                $result = 'L';
            }

            return [
                'result' => $result,
                'score' => "{$match->score_fulltime_home}x{$match->score_fulltime_away}",
                'opponent' => $opponentName,
                'date' => $match->utc_date,
                'is_home' => $isHome,
            ];
        })->toArray();
    }
}
