<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMatchResults;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:matches {competition_id? : The external ID of the competition (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import matches for a specific competition or all competitions from Football Data API';

    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service)
    {
        $competitionId = $this->argument('competition_id');

        if ($competitionId) {
            $this->importMatchesForCompetition($service, $competitionId);
        } else {
            $competitions = Competition::all();

            if ($competitions->isEmpty()) {
                $this->warn('No competitions found in database. Run import:competitions first.');
                return 0;
            }

            $this->info("Starting match import for {$competitions->count()} competitions...");

            foreach ($competitions as $index => $competition) {
                $this->importMatchesForCompetition($service, $competition->external_id);

                // Rate Limit Protection: Sleep 7 seconds between requests
                if ($index < $competitions->count() - 1) {
                    $this->info("Sleeping for 7 seconds to respect API rate limit...");
                    sleep(7);
                }

                $this->newLine();
            }
        }
    }

    private function importMatchesForCompetition(FootballDataService $service, int $competitionId)
    {
        $competition = Competition::where('external_id', $competitionId)->first();
        $compName = $competition ? $competition->name : "ID $competitionId";

        $this->info("Fetching matches for: {$compName}...");

        try {
            $response = $service->getCompetitionMatches($competitionId);
        } catch (\Exception $e) {
            $this->error("Failed to fetch matches for {$compName}: " . $e->getMessage());
            return;
        }

        if (!isset($response['matches'])) {
            $this->error("No matches found for {$compName}.");
            return;
        }

        $matches = $response['matches'];
        $count = count($matches);
        $this->info("Found {$count} matches. Saving...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($matches as $data) {
            if (isset($data['homeTeam']['id'])) {
                $this->ensureTeamExists($data['homeTeam']);
            }
            if (isset($data['awayTeam']['id'])) {
                $this->ensureTeamExists($data['awayTeam']);
            }

            // Correção Crítica: Prioriza regularTime se existir (para jogos com prorrogação/pênaltis)
            $homeScore = $data['score']['regularTime']['home'] ?? $data['score']['fullTime']['home'] ?? null;
            $awayScore = $data['score']['regularTime']['away'] ?? $data['score']['fullTime']['away'] ?? null;

            $match = FootballMatch::updateOrCreate(
                ['external_id' => $data['id']],
                [
                    'competition_id' => $data['competition']['id'],
                    'season_id' => $data['season']['id'],
                    'home_team_id' => $data['homeTeam']['id'] ?? null,
                    'away_team_id' => $data['awayTeam']['id'] ?? null,
                    'utc_date' => Carbon::parse($data['utcDate']),
                    'status' => $data['status'],
                    'matchday' => $data['matchday'],
                    'stage' => $data['stage'],
                    'group' => $data['group'],
                    'last_updated_api' => isset($data['lastUpdated']) ? Carbon::parse($data['lastUpdated']) : null,

                    'score_winner' => $data['score']['winner'] ?? null,
                    'score_duration' => $data['score']['duration'] ?? null,
                    'score_fulltime_home' => $homeScore,
                    'score_fulltime_away' => $awayScore,
                    'score_halftime_home' => $data['score']['halfTime']['home'] ?? null,
                    'score_halftime_away' => $data['score']['halfTime']['away'] ?? null,

                    'score_extratime_home' => $data['score']['extraTime']['home'] ?? null,
                    'score_extratime_away' => $data['score']['extraTime']['away'] ?? null,
                    'score_penalties_home' => $data['score']['penalties']['home'] ?? null,
                    'score_penalties_away' => $data['score']['penalties']['away'] ?? null,
                ]
            );

            if ($match->status === 'FINISHED' && ($match->wasRecentlyCreated || $match->wasChanged(['status', 'score_fulltime_home', 'score_fulltime_away', 'score_winner']))) {
                ProcessMatchResults::dispatch($match->external_id);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Matches for {$compName} imported successfully!");
    }

    private function ensureTeamExists(array $teamData)
    {
        Team::updateOrCreate(
            ['external_id' => $teamData['id']],
            [
                'name' => $teamData['name'],
                'short_name' => $teamData['shortName'] ?? null,
                'tla' => $teamData['tla'] ?? null,
                'crest' => $teamData['crest'] ?? null,
            ]
        );
    }
}
