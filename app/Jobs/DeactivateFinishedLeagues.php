<?php

namespace App\Jobs;

use App\Models\League;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeactivateFinishedLeagues implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Busca ligas ativas cuja temporada da competição já terminou
        League::where('is_active', true)
            ->whereHas('competition.currentSeason', function ($query) {
                $query->where('end_date', '<', now()->toDateString());
            })
            ->update(['is_active' => false]);
    }
}
