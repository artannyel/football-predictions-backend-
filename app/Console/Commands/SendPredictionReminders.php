<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class SendPredictionReminders extends Command
{
    protected $signature = 'send:prediction-reminders';
    protected $description = 'Send push notifications to users who have not predicted upcoming matches for today.';

    public function handle(OneSignalService $oneSignal)
    {
        $this->info('Starting prediction reminders...');

        // 1. Definir janela de tempo: Agora até o final do dia no Brasil (convertido para UTC)
        $now = now();
        $endOfDayBRT = Carbon::now('America/Sao_Paulo')->endOfDay()->setTimezone('UTC');

        // Se já passou do fim do dia no Brasil (madrugada UTC), não manda nada ou ajusta lógica
        if ($now > $endOfDayBRT) {
            $this->info('No more matches for today (BRT).');
            return;
        }

        $upcomingMatches = FootballMatch::where('utc_date', '>', $now)
            ->where('utc_date', '<=', $endOfDayBRT)
            ->whereIn('status', ['SCHEDULED', 'TIMED'])
            ->get()
            ->groupBy('competition_id');

        if ($upcomingMatches->isEmpty()) {
            $this->info('No upcoming matches for the rest of the day.');
            return;
        }

        $frontendUrl = env('FRONTEND_URL');
        $url = $frontendUrl ? "{$frontendUrl}/ligas" : null;

        foreach ($upcomingMatches as $competitionId => $matches) {
            $matchIds = $matches->pluck('external_id')->toArray();
            $matchCount = count($matchIds);

            $competition = Competition::where('external_id', $competitionId)->first();
            $compName = $competition ? $competition->name : 'Campeonato';

            $this->info("Processing {$compName} ({$competitionId}) - {$matchCount} matches...");

            // Busca todas as ligas ativas dessa competição
            $leagues = League::where('competition_id', $competitionId)
                ->where('is_active', true)
                ->get();

            if ($leagues->isEmpty()) continue;

            $usersToNotify = [];

            // Para cada liga, busca quem está devendo pelo menos 1 palpite
            foreach ($leagues as $league) {
                // Usuários desta liga que têm menos palpites NESTA LIGA do que o total de jogos do dia
                $pendingUsers = User::whereHas('leagues', function ($q) use ($league) {
                    $q->where('leagues.id', $league->id);
                })
                ->where(function ($q) use ($matchIds, $matchCount, $league) {
                    $q->whereHas('predictions', function ($sq) use ($matchIds, $league) {
                        $sq->whereIn('match_id', $matchIds)
                           ->where('league_id', $league->id);
                    }, '<', $matchCount); // Se tem menos palpites que jogos, tem pendência
                })
                ->pluck('id')
                ->toArray();

                foreach ($pendingUsers as $userId) {
                    $usersToNotify[$userId] = true; // Garante unicidade por competição
                }
            }

            if (!empty($usersToNotify)) {
                $userIds = array_keys($usersToNotify);

                foreach (array_chunk($userIds, 500) as $chunk) {
                    $title = "Jogos de hoje: {$compName} ⚽";
                    $message = "Você tem palpite(s) pendente(s) para a competição {$compName}. Não perca pontos! 🎯";

                    $oneSignal->sendToUsers($chunk, $title, $message, [
                        'type' => 'reminder',
                        'count' => $matchCount,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ], $url);
                }

                $this->info("Sent reminders to " . count($userIds) . " unique users.");
            }
        }

        $this->info('Reminders sent successfully.');
    }
}
