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
        protected string $resultType
    ) {}

    public function handle(OneSignalService $oneSignal): void
    {
        // Se não ganhou pontos, não notifica (para não ser chato)
        if ($this->points <= 0) {
            return;
        }

        $match = FootballMatch::where('external_id', $this->matchId)->with(['homeTeam', 'awayTeam'])->first();

        if (!$match) return;

        $home = $match->homeTeam->short_name ?? $match->homeTeam->name;
        $away = $match->awayTeam->short_name ?? $match->awayTeam->name;

        $title = "Fim de jogo: {$home} x {$away}";
        $message = $this->getMessage($this->points, $this->resultType);

        $oneSignal->sendToUsers([$this->userId], $title, $message, [
            'type' => 'match_result',
            'match_id' => $this->matchId,
            'points' => $this->points
        ]);
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
