<?php

namespace App\Actions;

use App\Models\FootballMatch;
use Illuminate\Database\Eloquent\Collection;

class ListStuckMatchesAction
{
    public function execute(): Collection
    {
        // Jogos IN_PLAY ou PAUSED que começaram há mais de 3 horas
        return FootballMatch::whereIn('status', ['IN_PLAY', 'PAUSED'])
            ->where('utc_date', '<', now()->subHours(3))
            ->with(['homeTeam', 'awayTeam', 'competition'])
            ->orderBy('utc_date', 'asc')
            ->get();
    }
}
