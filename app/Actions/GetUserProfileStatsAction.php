<?php

namespace App\Actions;

use App\Models\League;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetUserProfileStatsAction
{
    public function execute(User $user): array
    {
        $disk = config('filesystems.default');

        // 1. Estatísticas Globais
        $globalStats = DB::table('league_user')
            ->where('user_id', $user->id)
            ->selectRaw('
                SUM(points) as total_points,
                SUM(total_predictions) as total_predictions,
                SUM(exact_score_count) as exact_score_count,
                SUM(winner_diff_count) as winner_diff_count,
                SUM(winner_goal_count) as winner_goal_count,
                SUM(winner_only_count) as winner_only_count,
                SUM(error_count) as error_count
            ')
            ->first();

        $totalPoints = (int) ($globalStats->total_points ?? 0);
        $totalPredictions = (int) ($globalStats->total_predictions ?? 0);
        $average = $totalPredictions > 0 ? round($totalPoints / $totalPredictions, 2) : 0;

        $totalHits = $totalPredictions - ($globalStats->error_count ?? 0);

        // Contagem de Ligas
        $activeLeaguesCount = $user->leagues()->where('is_active', true)->count();
        $finishedLeaguesCount = $user->leagues()->where('is_active', false)->count();

        $radar = [
            'precision' => 0,
            'technique' => 0,
            'safety' => 0,
        ];

        if ($totalHits > 0) {
            $radar['precision'] = round(($globalStats->exact_score_count / $totalHits) * 100);
            $radar['technique'] = round((($globalStats->winner_diff_count + $globalStats->winner_goal_count) / $totalHits) * 100);
            $radar['safety'] = round(($globalStats->winner_only_count / $totalHits) * 100);
        }

        // 2. Histórico Recente
        $recentForm = $user->predictions()
            ->join('matches', 'predictions.match_id', '=', 'matches.external_id')
            ->where('matches.status', 'FINISHED')
            ->orderBy('matches.utc_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($prediction) {
                if ($prediction->points_earned >= 7) return 'P';
                if ($prediction->points_earned > 0) return 'W';
                return 'L';
            })
            ->values()
            ->toArray();

        // 3. Hall da Fama
        $hallOfFame = League::where(function ($query) use ($user) {
                $query->where('champion_id', $user->id)
                      ->orWhere('runner_up_id', $user->id)
                      ->orWhere('third_place_id', $user->id);
            })
            ->where('is_active', false)
            ->with('competition')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($league) use ($user, $disk) {
                $position = null;
                if ($league->champion_id === $user->id) $position = 1;
                elseif ($league->runner_up_id === $user->id) $position = 2;
                elseif ($league->third_place_id === $user->id) $position = 3;

                return [
                    'id' => $league->id,
                    'name' => $league->name,
                    'avatar_url' => $league->avatar ? asset(Storage::disk($disk)->url($league->avatar)) : null,
                    'competition_name' => $league->competition->name,
                    'position' => $position,
                    'year' => $league->updated_at->format('Y'),
                ];
            });

        return [
            'career' => [
                'total_points' => $totalPoints,
                'total_predictions' => $totalPredictions,
                'average_points' => $average,
                'win_rate' => $totalPredictions > 0 ? round(($totalHits / $totalPredictions) * 100, 1) : 0,
                'active_leagues_count' => $activeLeaguesCount,
                'finished_leagues_count' => $finishedLeaguesCount,
                'radar' => $radar,
                'recent_form' => $recentForm,
            ],
            'hall_of_fame' => $hallOfFame,
        ];
    }
}
