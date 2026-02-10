<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMatchResults;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateLiveMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:live-matches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update matches that are currently in play or scheduled for today.';

    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service)
    {
        // 1. Identificar competições com atividade agora
        // Jogos IN_PLAY, PAUSED ou SCHEDULED para começar em breve (ou que já deveriam ter começado)
        $activeCompetitionIds = FootballMatch::query()
            ->where(function ($query) {
                $query->whereIn('status', ['IN_PLAY', 'PAUSED'])
                      ->orWhere(function ($q) {
                          $q->where('status', 'SCHEDULED')
                            ->where('utc_date', '>=', now()->subHours(2)) // Jogos recentes
                            ->where('utc_date', '<=', now()->addMinutes(30)); // Começam em 30min
                      });
            })
            ->distinct()
            ->pluck('competition_id')
            ->toArray();

        if (empty($activeCompetitionIds)) {
            $this->info('No live matches found.');
            return;
        }

        // 2. Limitar a 5 competições por execução para respeitar Rate Limit (10 req/min)
        // Se tivermos mais de 5, pegamos aleatoriamente para dar chance a todas ao longo dos minutos
        // (Ou poderíamos ordenar por 'last_updated' se tivéssemos esse controle granular)
        $competitionsToUpdate = collect($activeCompetitionIds)
            ->random(min(count($activeCompetitionIds), 5));

        $this->info("Updating live matches for competitions: " . $competitionsToUpdate->implode(', '));

        foreach ($competitionsToUpdate as $competitionId) {
            $this->updateCompetitionMatches($service, $competitionId);

            // Pequeno sleep para não bombardear a API instantaneamente
            sleep(2);
        }
    }

    private function updateCompetitionMatches(FootballDataService $service, int $competitionId)
    {
        try {
            // Busca apenas jogos de HOJE para essa competição
            // Isso economiza banda e foca no que importa
            $today = now()->format('Y-m-d');

            $response = $service->getCompetitionMatches($competitionId, [
                'dateFrom' => $today,
                'dateTo' => $today,
            ]);

            if (!isset($response['matches'])) {
                return;
            }

            foreach ($response['matches'] as $data) {
                $match = FootballMatch::where('external_id', $data['id'])->first();

                if (!$match) continue;

                // Atualiza os dados
                $match->status = $data['status'];
                $match->matchday = $data['matchday'];
                $match->last_updated_api = isset($data['lastUpdated']) ? Carbon::parse($data['lastUpdated']) : now();

                // Score
                $match->score_winner = $data['score']['winner'] ?? null;
                $match->score_duration = $data['score']['duration'] ?? null;
                $match->score_fulltime_home = $data['score']['fullTime']['home'] ?? null;
                $match->score_fulltime_away = $data['score']['fullTime']['away'] ?? null;
                $match->score_halftime_home = $data['score']['halfTime']['home'] ?? null;
                $match->score_halftime_away = $data['score']['halfTime']['away'] ?? null;

                // Verifica se houve mudança relevante para salvar
                if ($match->isDirty()) {
                    $match->save();

                    // Se o jogo acabou AGORA (mudou para FINISHED nesta atualização), processa os pontos
                    if ($match->wasChanged('status') && $match->status === 'FINISHED') {
                        Log::info("Match {$match->external_id} finished. Dispatching processing job.");
                        ProcessMatchResults::dispatch($match->external_id);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to update live matches for competition {$competitionId}: " . $e->getMessage());
        }
    }
}
