<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\League;
use Illuminate\Database\Eloquent\Collection;

class ListUpcomingCompetitionMatchesAction
{
    public function execute(string $leagueId, string $userId): Collection
    {
        $league = League::findOrFail($leagueId);

        if (!$league->is_active) {
            return new Collection(); // Liga fechada, sem jogos disponíveis
        }

        return FootballMatch::with(['homeTeam', 'awayTeam'])
            ->where('competition_id', $league->competition_id)
            ->where('utc_date', '>', now())
            ->where('utc_date', '<=', now()->addDays(3))
            ->whereNot('status', 'POSTPONED')
            // Filtra jogos que o usuário JÁ palpitou NESTA liga
            ->whereDoesntHave('predictions', function ($query) use ($userId, $leagueId) {
                $query->where('user_id', $userId)
                      ->where('league_id', $leagueId);
            })
            ->orderBy('utc_date', 'asc')
            ->get();
    }
}
