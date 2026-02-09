<?php

namespace App\Actions;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Collection;

class ListActiveCompetitionsAction
{
    public function execute(): Collection
    {
        return Competition::with(['area', 'currentSeason'])
            ->whereHas('currentSeason', function ($query) {
                $query->where('end_date', '>=', now()->toDateString());
            })
            ->get();
    }
}
