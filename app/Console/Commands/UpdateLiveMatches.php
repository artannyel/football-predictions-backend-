<?php

namespace App\Console\Commands;

use App\Jobs\DeactivateFinishedLeagues;
use App\Jobs\ProcessMatchResults;
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
        $activeCompetitionIds = FootballMatch::query()
            ->where(function ($query) {
                $query->whereIn('status', ['IN_PLAY', 'PAUSED'])
                      ->orWhere(function ($q) {
                          $q->where('status', 'SCHEDULED')
                            ->where('utc_date', '>=', now()->subHours(2))
                            ->where('utc_date', '<=', now()->addMinutes(30));
                      });
            })
            ->distinct()
            ->pluck('competition_id')
            ->toArray();

        if (empty($activeCompetitionIds)) {
            $this->info('No live matches found.');
            return;
        }

        $competitionsToUpdate = collect($activeCompetitionIds)
            ->random(min(count($activeCompetitionIds), 5));

        $this->info("Updating live matches for competitions: " . $competitionsToUpdate->implode(', '));

        $finishedCompetitions = [];

        foreach ($competitionsToUpdate as $competitionId) {
            $hasFinishedMatch = $this->updateCompetitionMatches($service, $competitionId);
            if ($hasFinishedMatch) {
                $finishedCompetitions[] = $competitionId;
            }
            sleep(2);
        }

        // Dispara verificação de fechamento de liga para as competições afetadas
        foreach (array_unique($finishedCompetitions) as $compId) {
            DeactivateFinishedLeagues::dispatch($compId);
        }
    }

    private function updateCompetitionMatches(FootballDataService $service, int $competitionId): bool
    {
        $hasFinishedMatch = false;

        try {
            $today = now()->format('Y-m-d');
            $yesterday = now()->subDay()->format('Y-m-d');

            $response = $service->getCompetitionMatches($competitionId, [
                'dateFrom' => $yesterday,
                'dateTo' => $today,
            ]);

            if (!isset($response['matches'])) {
                return false;
            }

            foreach ($response['matches'] as $data) {
                $match = FootballMatch::where('external_id', $data['id'])->first();

                if (!$match) continue;

                $match->status = $data['status'];
                $match->matchday = $data['matchday'];
                $match->last_updated_api = isset($data['lastUpdated']) ? Carbon::parse($data['lastUpdated']) : now();

                $match->score_winner = $data['score']['winner'] ?? null;
                $match->score_duration = $data['score']['duration'] ?? null;
                $match->score_fulltime_home = $data['score']['fullTime']['home'] ?? null;
                $match->score_fulltime_away = $data['score']['fullTime']['away'] ?? null;
                $match->score_halftime_home = $data['score']['halfTime']['home'] ?? null;
                $match->score_halftime_away = $data['score']['halfTime']['away'] ?? null;

                if ($match->isDirty()) {
                    $match->save();

                    if ($match->wasChanged(['status', 'score_fulltime_home', 'score_fulltime_away'])) {
                        Log::info("Match {$match->external_id} updated. Dispatching processing job.");
                        ProcessMatchResults::dispatch($match->external_id);
                    }

                    // Se o jogo acabou AGORA, marca para verificar fechamento da liga
                    if ($match->wasChanged('status') && $match->status === 'FINISHED') {
                        $hasFinishedMatch = true;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to update live matches for competition {$competitionId}: " . $e->getMessage());
        }

        return $hasFinishedMatch;
    }
}
