<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\League;
use Carbon\Carbon;
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
        }

        $leagues = $query->with(['competition.currentSeason'])->get();

        if ($leagues->isEmpty()) {
            return;
        }

        $leaguesByCompetition = $leagues->groupBy('competition_id');

        foreach ($leaguesByCompetition as $compId => $groupLeagues) {
            $sampleLeague = $groupLeagues->first();
            $seasonId = $sampleLeague->competition->current_season_id;

            // 1. Verifica se há jogos pendentes (Futuros ou Ao Vivo)
            $hasPendingMatches = FootballMatch::where('competition_id', $compId)
                ->where('season_id', $seasonId)
                ->whereNotIn('status', ['FINISHED', 'CANCELED', 'AWARDED'])
                ->exists();

            if ($hasPendingMatches) {
                continue;
            }

            // 2. Verifica Buffer de Segurança (7 dias após o último jogo)
            $lastMatch = FootballMatch::where('competition_id', $compId)
                ->where('season_id', $seasonId)
                ->where('status', 'FINISHED')
                ->orderBy('utc_date', 'desc')
                ->first();

            $bufferDate = now()->subDays(7);

            if ($lastMatch) {
                // Se o último jogo foi há menos de 7 dias, mantém ativa
                if ($lastMatch->utc_date > $bufferDate) {
                    Log::info("Competition {$compId} finished recently ({$lastMatch->utc_date->format('Y-m-d')}). Keeping active for buffer period.");
                    continue;
                }
            } else {
                // Se não tem jogos finalizados (ex: cancelado ou erro), verifica data da season
                $seasonEndDate = $sampleLeague->competition->currentSeason->end_date;
                if ($seasonEndDate && Carbon::parse($seasonEndDate) > $bufferDate) {
                    continue;
                }
            }

            // 3. Desativa as ligas
            foreach ($groupLeagues as $league) {
                $this->processPodiumAndDeactivate($league);
            }

            Log::info("Deactivated " . count($groupLeagues) . " leagues for competition {$compId}.");
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
