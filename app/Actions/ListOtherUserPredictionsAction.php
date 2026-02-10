<?php

namespace App\Actions;

use App\Models\Prediction;
use Illuminate\Database\Eloquent\Collection;

class ListOtherUserPredictionsAction
{
    public function execute(string $userId, int $competitionId): Collection
    {
        return Prediction::where('user_id', $userId)
            ->whereHas('match', function ($query) use ($competitionId) {
                $query->where('competition_id', $competitionId)
                      ->where('utc_date', '<=', now()); // Apenas jogos que já começaram
            })
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->get()
            ->sortByDesc(function ($prediction) {
                return $prediction->match->utc_date;
            })
            ->values();
    }
}
