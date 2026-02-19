<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\League;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPredictionReminders extends Command
{
    protected $signature = 'send:prediction-reminders';
    protected $description = 'Send push notifications to users 1 hour before matches start.';

    public function handle(OneSignalService $oneSignal)
    {
        $this->info('Starting prediction reminders (1h before)...');

        $startWindow = now()->addMinutes(55);
        $endWindow = now()->addMinutes(65);

        $matches = FootballMatch::where('utc_date', '>=', $startWindow)
            ->where('utc_date', '<=', $endWindow)
            ->whereIn('status', ['SCHEDULED', 'TIMED'])
            ->with(['homeTeam', 'awayTeam', 'competition'])
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No matches starting in ~1h.');
            return;
        }

        $matchesByCompetition = $matches->groupBy('competition_id');

        foreach ($matchesByCompetition as $competitionId => $compMatches) {
            $matchIds = $compMatches->pluck('external_id')->toArray();

            $leagues = League::where('competition_id', $competitionId)
                ->where('is_active', true)
                ->get();

            if ($leagues->isEmpty()) continue;

            foreach ($leagues as $league) {
                $usersWithFullPredictions = DB::table('predictions')
                    ->where('league_id', $league->id)
                    ->whereIn('match_id', $matchIds)
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(DISTINCT match_id) = ?', [count($matchIds)])
                    ->pluck('user_id')
                    ->toArray();

                // Busca membros que querem receber lembretes
                $allMembers = DB::table('league_user')
                    ->join('users', 'league_user.user_id', '=', 'users.id')
                    ->where('league_user.league_id', $league->id)
                    ->where('users.notify_reminders', true) // Filtro de preferência
                    ->pluck('users.id')
                    ->toArray();

                $pendingUserIds = array_diff($allMembers, $usersWithFullPredictions);

                if (empty($pendingUserIds)) continue;

                $count = count($matchIds);
                $compName = $compMatches->first()->competition->name;

                if ($count === 1) {
                    $match = $compMatches->first();
                    $home = $match->homeTeam->short_name ?? $match->homeTeam->name;
                    $away = $match->awayTeam->short_name ?? $match->awayTeam->name;
                    $title = "⏰ Começa em 1h: {$home} x {$away}";
                    $message = "Faça seu palpite na liga {$league->name} agora!";
                } else {
                    $title = "⏰ {$count} jogos começam em 1h!";
                    $message = "Não esqueça de palpitar na liga {$league->name} ({$compName}).";
                }

                $frontendUrl = env('FRONTEND_URL');
                $url = $frontendUrl ? "{$frontendUrl}/ligas/{$league->id}" : null;

                foreach (array_chunk($pendingUserIds, 500) as $chunk) {
                    $oneSignal->sendToUsers($chunk, $title, $message, [
                        'type' => 'reminder',
                        'league_id' => $league->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ], $url);
                }

                $this->info("Sent reminders to " . count($pendingUserIds) . " users in league {$league->id}.");
            }
        }

        $this->info('Reminders sent successfully.');
    }
}
