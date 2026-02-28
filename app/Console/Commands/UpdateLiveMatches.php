<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMatchResults;
use App\Models\FootballMatch;
use App\Services\FootballDataService;
use App\Services\FirestoreService;
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
    public function handle(FootballDataService $service, FirestoreService $firestore)
    {
        $activeCompetitionIds = FootballMatch::query()
            ->where(function ($query) {
                $query->whereIn('status', ['IN_PLAY', 'PAUSED'])
                      ->orWhere(function ($q) {
                          $q->whereIn('status', ['SCHEDULED', 'TIMED'])
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

        foreach ($competitionsToUpdate as $competitionId) {
            $hasUpdates = $this->updateCompetitionMatches($service, $competitionId);

            if ($hasUpdates['changed']) {
                $firestore->signalCompetitionUpdate($competitionId, [
                    'trigger' => 'live_update',
                    'matches_affected' => $hasUpdates['count']
                ]);
            }

            sleep(2);
        }
    }

    /**
     * Retorna array ['changed' => bool, 'count' => int]
     */
    private function updateCompetitionMatches(FootballDataService $service, int $competitionId): array
    {
        $result = ['changed' => false, 'count' => 0];

        try {
            $today = now()->format('Y-m-d');
            $yesterday = now()->subDay()->format('Y-m-d');

            $response = $service->getCompetitionMatches($competitionId, [
                'dateFrom' => $yesterday,
                'dateTo' => $today,
            ]);

            if (!isset($response['matches'])) {
                return $result;
            }

            foreach ($response['matches'] as $data) {
                $match = FootballMatch::where('external_id', $data['id'])->first();

                if (!$match) continue;

                // Proteção contra sobrescrita manual
                if ($match->is_manual_update) {
                    continue;
                }

                $match->status = $data['status'];
                $match->matchday = $data['matchday'];
                $match->last_updated_api = isset($data['lastUpdated']) ? Carbon::parse($data['lastUpdated']) : now();

                // Lógica de Score baseada na Duração
                $duration = $data['score']['duration'] ?? 'REGULAR';

                if ($duration === 'REGULAR') {
                    $homeScore = $data['score']['fullTime']['home'] ?? null;
                    $awayScore = $data['score']['fullTime']['away'] ?? null;
                } else {
                    $homeScore = $data['score']['regularTime']['home'] ?? $data['score']['fullTime']['home'] ?? null;
                    $awayScore = $data['score']['regularTime']['away'] ?? $data['score']['fullTime']['away'] ?? null;
                }

                $match->score_winner = $data['score']['winner'] ?? null;
                $match->score_duration = $duration;
                $match->score_fulltime_home = $homeScore;
                $match->score_fulltime_away = $awayScore;
                $match->score_halftime_home = $data['score']['halfTime']['home'] ?? null;
                $match->score_halftime_away = $data['score']['halfTime']['away'] ?? null;

                if ($match->isDirty()) {
                    $match->save();

                    if ($match->wasChanged(['status', 'score_fulltime_home', 'score_fulltime_away'])) {
                        Log::info("Match {$match->external_id} updated. Dispatching processing job.");
                        ProcessMatchResults::dispatch($match->external_id);

                        $result['changed'] = true;
                        $result['count']++;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to update live matches for competition {$competitionId}: " . $e->getMessage());
        }

        return $result;
    }
}
