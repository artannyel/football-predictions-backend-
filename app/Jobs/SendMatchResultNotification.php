<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMatchResultNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $userId,
        protected int $matchId,
        protected int $points,
        protected string $resultType,
        protected string $leagueId,
        protected array $newBadges = [],
        protected array $revokedBadges = []
    ) {}

    public function handle(OneSignalService $oneSignal): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->notify_results) {
            return;
        }

        if ($this->points <= 0 && empty($this->newBadges) && empty($this->revokedBadges)) {
            return;
        }

        $match = FootballMatch::where('external_id', $this->matchId)->with(['homeTeam', 'awayTeam'])->first();

        if (!$match) return;

        $home = $match->homeTeam->short_name ?? $match->homeTeam->name;
        $away = $match->awayTeam->short_name ?? $match->awayTeam->name;

        $title = "Fim de jogo: {$home} x {$away}";
        $message = $this->getMessage($this->points, $this->resultType);

        if (!empty($this->newBadges)) {
            $badgeNames = array_map(fn($b) => $b->name, $this->newBadges);
            $message .= "\n🏅 Conquista: " . implode(', ', $badgeNames) . "!";
        }

        if (!empty($this->revokedBadges)) {
            $badgeNames = array_map(fn($b) => $b->name, $this->revokedBadges);
            $message .= "\n⚠️ Correção: A medalha " . implode(', ', $badgeNames) . " foi removida devido à mudança no placar.";
        }

        $frontendUrl = env('FRONTEND_URL');
        $url = $frontendUrl ? "{$frontendUrl}/ligas/{$this->leagueId}" : null;

        $oneSignal->sendToUsers([$this->userId], $title, $message, [
            'type' => 'match_result',
            'match_id' => $this->matchId,
            'points' => $this->points,
            'league_id' => $this->leagueId,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'badges_awarded' => array_map(fn($b) => $b->slug, $this->newBadges),
            'badges_revoked' => array_map(fn($b) => $b->slug, $this->revokedBadges),
            'android_group' => 'results_league_' . $this->leagueId, // Agrupamento por Liga
            'thread_id' => 'results_league_' . $this->leagueId,
            'android_group_message' => [
                'en' => 'You have $[notif_count] new results in this league.',
                'pt' => 'Você tem $[notif_count] novos resultados nesta liga.',
            ],
        ], $url);
    }

    private function getMessage(int $points, string $type): string
    {
        return match ($type) {
            'EXACT_SCORE' => "Na mosca! 🎯 Você acertou o placar exato e ganhou {$points} pontos!",
            'WINNER_DIFF' => "Boa! ✅ Você acertou o vencedor e o saldo de gols. +{$points} pontos.",
            'WINNER_GOAL' => "Quase lá! ⚽ Você acertou o vencedor e os gols de um time. +{$points} pontos.",
            'WINNER_ONLY' => "Pelo menos acertou quem ganhou! 🏅 +{$points} ponto.",
            default => "Você ganhou {$points} pontos neste jogo.",
        };
    }
}
