<?php

namespace App\Jobs;

use App\Actions\GetMatchPredictionStatsAction;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use App\Services\BadgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateLeagueBadges implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $leagueId,
        protected ?string $badgeSlug = null
    ) {}

    public function handle(BadgeService $badgeService, GetMatchPredictionStatsAction $statsAction): void
    {
        $league = League::find($this->leagueId);
        if (!$league) return;

        Log::channel('recalculation')->info("Recalculating badges for League {$league->id} ({$league->name})...");

        $matches = FootballMatch::where('competition_id', $league->competition_id)
            ->where('status', 'FINISHED')
            ->get();

        foreach ($matches as $match) {
            $matchStats = $statsAction->execute($match->external_id);

            $predictions = Prediction::where('match_id', $match->external_id)
                ->where('league_id', $league->id)
                ->get();

            if ($predictions->isEmpty()) continue;

            $badgeService->syncBadgesBatch($predictions, $match, $matchStats);
        }

        Log::channel('recalculation')->info("League {$league->id} badges recalculated.");
    }
}
