<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetGlobalRankingAction
{
    public function execute(User $currentUser, string $period, int $limit = 10): array
    {
        $disk = config('filesystems.default');

        // 1. Top Ranking (Hall da Fama)
        $topUsers = DB::table('user_stats')
            ->join('users', 'user_stats.user_id', '=', 'users.id')
            ->where('period', $period)
            ->select(
                'users.id as user_id',
                'users.name',
                'users.photo_url',
                'user_stats.points',
                'user_stats.exact_score_count',
                'user_stats.winner_diff_count',
                'user_stats.winner_goal_count',
                'user_stats.winner_only_count',
                'user_stats.error_count',
                'user_stats.total_predictions'
            )
            ->orderBy('exact_score_count', 'desc')
            ->orderBy('winner_diff_count', 'desc')
            ->orderBy('winner_goal_count', 'desc')
            ->orderBy('winner_only_count', 'desc')
            ->orderBy('error_count', 'asc')
            ->limit($limit)
            ->get();

        $formattedTop = $topUsers->map(function ($row, $index) use ($disk) {
            return [
                'rank' => $index + 1,
                'user_id' => $row->user_id,
                'name' => $row->name,
                'photo_url' => $row->photo_url ? asset(Storage::disk($disk)->url($row->photo_url)) : null,
                'points' => $row->points,
                'stats' => [
                    'exact_score' => $row->exact_score_count,
                    'winner_diff' => $row->winner_diff_count,
                    'winner_goal' => $row->winner_goal_count,
                    'winner_only' => $row->winner_only_count,
                    'errors' => $row->error_count,
                    'total' => $row->total_predictions,
                ]
            ];
        });

        // 2. Minha Posição
        $myStats = DB::table('user_stats')
            ->where('user_id', $currentUser->id)
            ->where('period', $period)
            ->first();

        $myRankData = null;
        if ($myStats) {
            $subquery = DB::table('user_stats')
                ->select('user_id', DB::raw('ROW_NUMBER() OVER (ORDER BY exact_score_count DESC, winner_diff_count DESC, winner_goal_count DESC, winner_only_count DESC, error_count ASC) as rank'))
                ->where('period', $period);

            $rankRow = DB::table(DB::raw("({$subquery->toSql()}) as ranked"))
                ->mergeBindings($subquery)
                ->where('user_id', $currentUser->id)
                ->first();

            if ($rankRow) {
                $myRankData = [
                    'rank' => $rankRow->rank,
                    'points' => $myStats->points,
                    'stats' => [
                        'exact_score' => $myStats->exact_score_count,
                        'winner_diff' => $myStats->winner_diff_count,
                        'winner_goal' => $myStats->winner_goal_count,
                        'winner_only' => $myStats->winner_only_count,
                        'errors' => $myStats->error_count,
                        'total' => $myStats->total_predictions,
                    ]
                ];
            }
        }

        return [
            'period' => $period,
            'my_rank' => $myRankData,
            'top_list' => $formattedTop,
        ];
    }
}
