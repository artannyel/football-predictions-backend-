<?php

namespace App\Actions;

use App\Models\Prediction;
use Illuminate\Database\Eloquent\Collection;

class ListOtherUserPredictionsAction
{
    public function execute(string $targetUserId, string $leagueId): Collection
    {
        return Prediction::where('user_id', $targetUserId)
            ->where('league_id', $leagueId)
            ->whereHas('match', function ($query) {
                $query->where('utc_date', '<=', now()); // Apenas jogos que já começaram
            })
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->get()
            ->sortByDesc(function ($prediction) {
                return $prediction->match->utc_date;
            })
            ->values();
    }
}
