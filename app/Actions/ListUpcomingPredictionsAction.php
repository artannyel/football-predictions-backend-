<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ListUpcomingPredictionsAction
{
    public function execute(User $user, ?int $competitionId = null): Collection
    {
        $query = $user->predictions()
            ->whereHas('match', function ($q) use ($competitionId) {
                $q->where('utc_date', '>', now())
                  ->where('utc_date', '<=', now()->addDays(3));

                if ($competitionId) {
                    $q->where('competition_id', $competitionId);
                }
            })
            ->with(['match.homeTeam', 'match.awayTeam']);

        return $query->get()
            ->sortBy(function ($prediction) {
                return $prediction->match->utc_date;
            })
            ->values();
    }
}
