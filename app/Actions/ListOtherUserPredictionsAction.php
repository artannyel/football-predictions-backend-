<?php

namespace App\Actions;

use App\Models\Prediction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListOtherUserPredictionsAction
{
    public function execute(string $targetUserId, string $leagueId, string $currentUserId, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = Prediction::select('predictions.*')
            ->join('matches', 'predictions.match_id', '=', 'matches.external_id')
            ->where('predictions.user_id', $targetUserId)
            ->where('predictions.league_id', $leagueId)
            ->where('matches.utc_date', '<=', now()) // Apenas jogos que já começaram
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->orderBy('matches.utc_date', 'desc')
            ->paginate($perPage);

        $matchIds = $paginator->getCollection()->pluck('match_id')->toArray();

        if (empty($matchIds)) {
            return $paginator;
        }

        $myPredictions = Prediction::where('user_id', $currentUserId)
            ->where('league_id', $leagueId)
            ->whereIn('match_id', $matchIds)
            ->get()
            ->keyBy('match_id');

        $paginator->getCollection()->transform(function ($prediction) use ($myPredictions) {
            $prediction->my_prediction = $myPredictions->get($prediction->match_id);
            return $prediction;
        });

        return $paginator;
    }
}
