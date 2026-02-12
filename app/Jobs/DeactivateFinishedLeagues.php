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
            // Fallback: foca nas que já passaram da data
            $query->whereHas('competition.currentSeason', function ($q) {
                $q->where('end_date', '<=', now()->toDateString());
            });
        }

        // Carrega apenas os dados necessários para agrupar
        $leagues = $query->with('competition')->get();

        if ($leagues->isEmpty()) {
            return;
        }

        // Agrupa por competition_id para evitar queries repetidas
        $leaguesByCompetition = $leagues->groupBy('competition_id');

        foreach ($leaguesByCompetition as $compId => $groupLeagues) {
            // Pega a primeira liga do grupo para acessar os dados da competição/season
            // (Assumindo que todas as ligas da mesma competição apontam para a mesma season atual, o que é verdade pela estrutura)
            $sampleLeague = $groupLeagues->first();

            // Verifica UMA VEZ por competição
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

            // Atualiza TODAS as ligas dessa competição de uma vez
            $leagueIds = $groupLeagues->pluck('id')->toArray();

            League::whereIn('id', $leagueIds)->update(['is_active' => false]);

            Log::info("Deactivated " . count($leagueIds) . " leagues for competition {$compId} (Season Finished).");
        }
    }
}
