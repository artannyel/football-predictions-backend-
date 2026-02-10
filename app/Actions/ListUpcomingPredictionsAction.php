<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListUpcomingPredictionsAction
{
    public function execute(User $user, string $leagueId): Collection
    {
        return $user->predictions()
            ->where('league_id', $leagueId)
            ->whereHas('match', function ($q) {
                $q->where('utc_date', '>', now())
                  ->where('utc_date', '<=', now()->addDays(3));
            })
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->get()
            ->sortBy(function ($prediction) {
                return $prediction->match->utc_date;
            })
            ->values();
    }
}
