<?php

namespace App\Actions;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Collection;

class ListActiveCompetitionsAction
{
    public function execute(): Collection
    {
        return Competition::with(['area', 'currentSeason'])
            ->whereHas('matches', function ($query) {
                $query->where('utc_date', '>=', now()->startOfDay())
                      ->whereIn('status', ['SCHEDULED', 'TIMED']);
            })
            ->get();
    }
}
