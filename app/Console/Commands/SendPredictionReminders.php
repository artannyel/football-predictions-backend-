<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\League;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendPredictionReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:prediction-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send push notifications to users who have not predicted upcoming matches.';

    /**
     * Execute the console command.
     */
    public function handle(OneSignalService $oneSignal)
    {
        $this->info('Starting prediction reminders...');

        // 1. Buscar jogos das próximas 24h
        $upcomingMatches = FootballMatch::where('utc_date', '>', now())
            ->where('utc_date', '<=', now()->addHours(24))
            ->whereIn('status', ['SCHEDULED', 'TIMED'])
            ->get()
            ->groupBy('competition_id');

        if ($upcomingMatches->isEmpty()) {
            $this->info('No upcoming matches found.');
            return;
        }

        // 2. Iterar por competição para processar ligas relevantes
        foreach ($upcomingMatches as $competitionId => $matches) {
            $matchIds = $matches->pluck('external_id')->toArray();
            $matchCount = count($matchIds);

            $this->info("Processing competition {$competitionId} with {$matchCount} matches...");

            // 3. Buscar ligas ativas dessa competição
            $leagues = League::where('competition_id', $competitionId)
                ->where('is_active', true)
                ->pluck('id');

            if ($leagues->isEmpty()) {
                continue;
            }

            // 4. Buscar usuários dessas ligas que NÃO palpitaram em TODOS os jogos
            User::whereHas('leagues', function (Builder $query) use ($leagues) {
                $query->whereIn('leagues.id', $leagues);
            })
            ->where(function (Builder $query) use ($matchIds, $matchCount) {
                $query->whereHas('predictions', function (Builder $q) use ($matchIds) {
                    $q->whereIn('match_id', $matchIds);
                }, '<', $matchCount);
            })
            ->chunk(500, function ($users) use ($oneSignal, $matchCount) {
                $userIds = $users->pluck('id')->toArray();

                if (empty($userIds)) return;

                $title = "Jogos de hoje! ⚽";
                $message = "Você tem palpites pendentes para {$matchCount} jogos que começam em breve. Não perca pontos!";

                $oneSignal->sendToUsers($userIds, $title, $message, [
                    'type' => 'reminder',
                    'count' => $matchCount
                ]);

                $this->info("Sent reminders to " . count($userIds) . " users.");
            });
        }

        $this->info('Reminders sent successfully.');
    }
}
