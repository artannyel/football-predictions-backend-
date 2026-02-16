<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\League;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeactivateFinishedLeagues implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected ?int $competitionId = null) {}

    public function handle(): void
    {
        $query = League::where('is_active', true);

        if ($this->competitionId) {
            $query->where('competition_id', $this->competitionId);
        } else {
            $query->whereHas('competition.currentSeason', function ($q) {
                $q->where('end_date', '<=', now()->toDateString());
            });
        }

        $leagues = $query->with('competition')->get();

        if ($leagues->isEmpty()) {
            return;
        }

        $leaguesByCompetition = $leagues->groupBy('competition_id');

        foreach ($leaguesByCompetition as $compId => $groupLeagues) {
            $sampleLeague = $groupLeagues->first();

            $hasPendingMatches = FootballMatch::where('competition_id', $compId)
                ->where('season_id', $sampleLeague->competition->current_season_id)
                ->whereNotIn('status', ['FINISHED', 'CANCELED', 'AWARDED'])
                ->exists();

            if ($hasPendingMatches) {
                if ($this->competitionId) {
                    Log::info("Competition {$compId} not finished yet. Pending matches exist.");
                }
                continue;
            }

            // Processa cada liga individualmente para definir o pódio
            foreach ($groupLeagues as $league) {
                $this->processPodiumAndDeactivate($league);
            }

            Log::info("Processed " . count($groupLeagues) . " leagues for competition {$compId} (Season Finished).");
        }
    }

    private function processPodiumAndDeactivate(League $league)
    {
        // Busca top 3 membros
        $topMembers = $league->members()
            ->orderByPivot('points', 'desc')
            ->orderByPivot('exact_score_count', 'desc')
            ->orderByPivot('winner_diff_count', 'desc')
            ->orderByPivot('winner_goal_count', 'desc')
            ->orderByPivot('winner_only_count', 'desc')
            ->orderByPivot('error_count', 'asc')
            ->limit(3)
            ->get();

        $updateData = ['is_active' => false];

        if ($topMembers->isNotEmpty()) {
            $updateData['champion_id'] = $topMembers[0]->id;

            if (isset($topMembers[1])) {
                $updateData['runner_up_id'] = $topMembers[1]->id;
            }

            if (isset($topMembers[2])) {
                $updateData['third_place_id'] = $topMembers[2]->id;
            }
        }

        $league->update($updateData);
    }
}
