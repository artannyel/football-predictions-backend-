<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMatchResults;
use App\Models\FootballMatch;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateDailyMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:daily-matches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all matches scheduled for today to ensure data consistency.';

    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service)
    {
        $today = now()->format('Y-m-d');

        // 1. Identificar competições com jogos HOJE
        $competitionIds = FootballMatch::whereDate('utc_date', $today)
            ->distinct()
            ->pluck('competition_id')
            ->toArray();

        if (empty($competitionIds)) {
            $this->info('No matches scheduled for today.');
            return;
        }

        $this->info("Updating daily matches for competitions: " . implode(', ', $competitionIds));

        foreach ($competitionIds as $competitionId) {
            $this->updateCompetitionMatches($service, $competitionId, $today);

            // Sleep para respeitar rate limit
            sleep(2);
        }
    }

    private function updateCompetitionMatches(FootballDataService $service, int $competitionId, string $date)
    {
        try {
            $response = $service->getCompetitionMatches($competitionId, [
                'dateFrom' => $date,
                'dateTo' => $date,
            ]);

            if (!isset($response['matches'])) {
                return;
            }

            foreach ($response['matches'] as $data) {
                $match = FootballMatch::where('external_id', $data['id'])->first();

                if (!$match) continue;

                // Atualiza dados básicos
                $match->utc_date = Carbon::parse($data['utcDate']);
                $match->status = $data['status'];
                $match->matchday = $data['matchday'];
                $match->last_updated_api = isset($data['lastUpdated']) ? Carbon::parse($data['lastUpdated']) : now();

                // Correção Crítica: Prioriza regularTime se existir
                $homeScore = $data['score']['regularTime']['home'] ?? $data['score']['fullTime']['home'] ?? null;
                $awayScore = $data['score']['regularTime']['away'] ?? $data['score']['fullTime']['away'] ?? null;

                $match->score_winner = $data['score']['winner'] ?? null;
                $match->score_duration = $data['score']['duration'] ?? null;
                $match->score_fulltime_home = $homeScore;
                $match->score_fulltime_away = $awayScore;
                $match->score_halftime_home = $data['score']['halfTime']['home'] ?? null;
                $match->score_halftime_away = $data['score']['halfTime']['away'] ?? null;

                if ($match->isDirty()) {
                    $match->save();

                    // Se finalizou ou mudou placar, processa pontos
                    if ($match->status === 'FINISHED' && ($match->wasChanged(['status', 'score_fulltime_home', 'score_fulltime_away', 'score_winner']))) {
                        Log::info("Match {$match->external_id} updated (daily update). Dispatching processing job.");
                        ProcessMatchResults::dispatch($match->external_id);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to update daily matches for competition {$competitionId}: " . $e->getMessage());
        }
    }
}
