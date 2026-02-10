<?php

namespace App\Actions;

use App\Models\Prediction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListOtherUserPredictionsAction
{
    public function execute(string $targetUserId, string $leagueId, int $perPage = 20): LengthAwarePaginator
    {
        return Prediction::select('predictions.*')
            ->join('matches', 'predictions.match_id', '=', 'matches.external_id')
            ->where('predictions.user_id', $targetUserId)
            ->where('predictions.league_id', $leagueId)
            ->where('matches.utc_date', '<=', now()) // Apenas jogos que já começaram
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->orderBy('matches.utc_date', 'desc')
            ->paginate($perPage);
    }
}
