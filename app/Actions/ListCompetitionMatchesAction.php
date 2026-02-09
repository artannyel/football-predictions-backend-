<?php

namespace App\Actions;

use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Collection;

class ListCompetitionMatchesAction
{
    public function execute(int $competitionId): Collection
    {
        return FootballMatch::with(['homeTeam', 'awayTeam'])
            ->where('competition_id', $competitionId)
            ->orderBy('utc_date', 'asc')
            ->get();
    }
}
