<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Competition;
use App\Models\Season;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCompetitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:competitions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import competitions, areas and current seasons from Football Data API';

    /**
     * Execute the console command.
     */
    public function handle(FootballDataService $service)
    {
        $this->info('Fetching competitions from API...');

        try {
            $response = $service->getCompetitions();
        } catch (\Exception $e) {
            $this->error('Failed to fetch data: ' . $e->getMessage());
            return 1;
        }

        if (!isset($response['competitions'])) {
            $this->error('Invalid response format.');
            return 1;
        }

        $competitions = $response['competitions'];
        $count = count($competitions);
        $this->info("Found {$count} competitions. Processing...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($competitions as $data) {
            DB::transaction(function () use ($data) {
                // 1. Import Area
                $areaData = $data['area'];
                $area = Area::updateOrCreate(
                    ['external_id' => $areaData['id']],
                    [
                        'name' => $areaData['name'],
                        'code' => $areaData['code'],
                        'flag' => $areaData['flag'],
                    ]
                );

                // 2. Import Competition
                $competition = Competition::updateOrCreate(
                    ['external_id' => $data['id']],
                    [
                        'area_id' => $area->external_id,
                        'name' => $data['name'],
                        'code' => $data['code'],
                        'type' => $data['type'],
                        'emblem' => $data['emblem'],
                        'number_of_available_seasons' => $data['numberOfAvailableSeasons'] ?? 0,
                        'last_updated_api' => isset($data['lastUpdated']) ? Carbon::parse($data['lastUpdated']) : null,
                    ]
                );

                // 3. Import Current Season (if exists)
                if (isset($data['currentSeason'])) {
                    $seasonData = $data['currentSeason'];

                    $season = Season::updateOrCreate(
                        ['external_id' => $seasonData['id']],
                        [
                            'competition_id' => $competition->external_id,
                            'start_date' => $seasonData['startDate'],
                            'end_date' => $seasonData['endDate'],
                            'current_matchday' => $seasonData['currentMatchday'],
                            'winner_external_id' => isset($seasonData['winner']) ? $seasonData['winner']['id'] : null,
                        ]
                    );

                    // Update competition with current season link
                    $competition->current_season_id = $season->external_id;
                    $competition->save();
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Competitions imported successfully!');
    }
}
