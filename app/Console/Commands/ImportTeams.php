<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\Team;
use App\Services\FootballDataService;
use Illuminate\Console\Command;

class ImportTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:teams {competition_id? : The external ID of the competition (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import teams for a specific competition or all competitions from Football Data API';

    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service)
    {
        $competitionId = $this->argument('competition_id');

        if ($competitionId) {
            $this->importTeamsForCompetition($service, $competitionId);
        } else {
            $competitions = Competition::all();

            if ($competitions->isEmpty()) {
                $this->warn('No competitions found in database. Run import:competitions first.');
                return 0;
            }

            $this->info("Starting import for {$competitions->count()} competitions...");

            foreach ($competitions as $competition) {
                $this->importTeamsForCompetition($service, $competition->external_id);
                $this->newLine();
            }
        }
    }

    private function importTeamsForCompetition(FootballDataService $service, int $competitionId)
    {
        $competition = Competition::where('external_id', $competitionId)->first();
        $compName = $competition ? $competition->name : "ID $competitionId";

        $this->info("Fetching teams for: {$compName}...");

        try {
            $response = $service->getCompetitionTeams($competitionId);
        } catch (\Exception $e) {
            $this->error("Failed to fetch data for {$compName}: " . $e->getMessage());
            return;
        }

        if (!isset($response['teams'])) {
            $this->error("No teams found for {$compName}.");
            return;
        }

        $teams = $response['teams'];
        $count = count($teams);
        $this->info("Found {$count} teams. Saving...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($teams as $data) {
            Team::updateOrCreate(
                ['external_id' => $data['id']],
                [
                    'name' => $data['name'],
                    'short_name' => $data['shortName'],
                    'tla' => $data['tla'],
                    'crest' => $data['crest'],
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Teams for {$compName} imported successfully!");
    }
}
