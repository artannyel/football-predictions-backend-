<?php

namespace App\Jobs;

use App\Models\Badge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AwardRankingBadges implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $period, // '2026-02' ou '2026'
        protected string $type    // 'monthly' ou 'season'
    ) {}

    public function handle(): void
    {
        Log::channel('recalculation')->info("Awarding {$this->type} badges for period {$this->period}...");

        // 1. Buscar Top 10 do período
        $topUsers = DB::table('user_stats')
            ->where('period', $this->period)
            ->orderBy('exact_score_count', 'desc')
            ->orderBy('winner_diff_count', 'desc')
            ->orderBy('winner_goal_count', 'desc')
            ->orderBy('winner_only_count', 'desc')
            ->orderBy('error_count', 'asc')
            ->orderBy('points', 'desc')
            ->limit(10)
            ->get();

        if ($topUsers->isEmpty()) {
            Log::channel('recalculation')->info("No stats found for period {$this->period}.");
            return;
        }

        // 2. Carregar Badges
        $badges = [
            'top_1' => Badge::where('slug', "{$this->type}_top_1")->first(),
            'top_3' => Badge::where('slug', "{$this->type}_top_3")->first(),
            'top_10' => Badge::where('slug', "{$this->type}_top_10")->first(),
        ];

        foreach ($topUsers as $index => $userStat) {
            $rank = $index + 1;
            $userId = $userStat->user_id;

            // Define quais badges o usuário ganha
            // Regra: Ganha apenas a maior badge? Ou acumula?
            // Geralmente acumula (Top 1 também é Top 3 e Top 10).
            // Mas para não poluir, vamos dar apenas a maior conquista.

            $badgeToAward = null;

            if ($rank === 1) {
                $badgeToAward = $badges['top_1'];
            } elseif ($rank <= 3) {
                $badgeToAward = $badges['top_3'];
            } else {
                $badgeToAward = $badges['top_10'];
            }

            if ($badgeToAward) {
                $this->awardBadge($userId, $badgeToAward);
            }
        }

        Log::channel('recalculation')->info("Badges awarded successfully.");
    }

    private function awardBadge($userId, $badge)
    {
        // Verifica se já ganhou essa badge para este período (evita duplicidade se rodar 2x)
        // Como user_badges não tem coluna 'period', usamos created_at aproximado ou confiamos no Job único.
        // Mas espere! Se o usuário ganhar "Rei do Mês" em Fev e Março, ele deve ter 2 registros.
        // A tabela user_badges tem (user_id, badge_id, league_id, match_id).
        // Para badges globais, league_id e match_id são null.

        // Problema: Como saber se esse registro é de Fev ou Março?
        // Solução: Podemos usar o created_at.
        // Ou melhor: Adicionar uma coluna 'metadata' ou 'period' na user_badges?
        // Como não queremos alterar a tabela agora, vamos confiar no created_at.

        // Verifica se já ganhou essa badge nos últimos 5 dias (janela de processamento)
        $exists = DB::table('user_badges')
            ->where('user_id', $userId)
            ->where('badge_id', $badge->id)
            ->whereNull('league_id')
            ->where('created_at', '>=', now()->subDays(5))
            ->exists();

        if (!$exists) {
            DB::table('user_badges')->insert([
                'user_id' => $userId,
                'badge_id' => $badge->id,
                'league_id' => null,
                'match_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::channel('recalculation')->info("Awarded {$badge->slug} to User {$userId}");
        }
    }
}
