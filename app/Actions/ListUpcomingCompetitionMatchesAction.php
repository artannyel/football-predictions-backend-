<?php

namespace App\Actions;

use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Collection;

class ListUpcomingCompetitionMatchesAction
{
    public function execute(int $competitionId, string $userId): Collection
    {
        return FootballMatch::with(['homeTeam', 'awayTeam'])
            ->where('competition_id', $competitionId)
            ->where('utc_date', '>', now())
            ->where('utc_date', '<=', now()->addDays(3))
            ->whereDoesntHave('predictions', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orderBy('utc_date', 'asc')
            ->get();
    }
}
