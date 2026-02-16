<?php

namespace App\Jobs;

use App\Models\League;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateBadges implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ?string $badgeSlug = null) {}

    public function handle(): void
    {
        Log::channel('recalculation')->info('Dispatching badge recalculation jobs for all leagues...');

        League::chunk(100, function ($leagues) {
            foreach ($leagues as $league) {
                RecalculateLeagueBadges::dispatch($league->id, $this->badgeSlug);
            }
        });

        Log::channel('recalculation')->info('All league badge recalculation jobs dispatched.');
    }
}
