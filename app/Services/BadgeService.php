<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeService
{
    protected $badges;

    public function __construct()
    {
        $this->badges = Badge::all()->keyBy('slug');
    }

    /**
     * Sincroniza as medalhas do usuário para um jogo específico.
     *
     * @param array $matchStats Estatísticas dos palpites do jogo (opcional)
     * @return array{awarded: array, revoked: array}
     */
    public function syncBadges(Prediction $prediction, FootballMatch $match, array $matchStats = []): array
    {
        $deservedBadgesSlugs = $this->calculateDeservedBadges($prediction, $match, $matchStats);

        $existingBadges = DB::table('user_badges')
            ->where('user_id', $prediction->user_id)
            ->where('match_id', $prediction->match_id)
            ->pluck('badge_id')
            ->toArray();

        $existingBadgesSlugs = [];
        foreach ($this->badges as $slug => $badge) {
            if (in_array($badge->id, $existingBadges)) {
                $existingBadgesSlugs[] = $slug;
            }
        }

        $awarded = [];
        $revoked = [];

        foreach ($deservedBadgesSlugs as $slug) {
            if (!in_array($slug, $existingBadgesSlugs)) {
                $this->award($prediction, $slug);
                $awarded[] = $this->badges->get($slug);
            }
        }

        foreach ($existingBadgesSlugs as $slug) {
            if (!in_array($slug, $deservedBadgesSlugs)) {
                $this->revoke($prediction, $slug);
                $revoked[] = $this->badges->get($slug);
            }
        }

        return ['awarded' => $awarded, 'revoked' => $revoked];
    }

    private function calculateDeservedBadges(Prediction $prediction, FootballMatch $match, array $stats): array
    {
        $slugs = [];

        if ($prediction->points_earned <= 0) {
            return [];
        }

        // 1. Sniper
        if ($prediction->result_type === 'EXACT_SCORE') {
            $slugs[] = 'sniper';
        }

        // 2. Ousado (Empate)
        if ($match->score_fulltime_home === $match->score_fulltime_away) {
            $slugs[] = 'ousado';
        }

        // 3. Zebra (Baseada na % da galera)
        // Se acertou o vencedor (qualquer tipo de acerto que não seja empate, pois empate é Ousado)
        // E a % desse resultado foi < 15%
        if (!empty($stats) && $match->score_fulltime_home !== $match->score_fulltime_away) {
            $isHomeWin = $match->score_fulltime_home > $match->score_fulltime_away;

            // Se foi vitória do mandante e a % foi baixa
            if ($isHomeWin && ($stats['home_win_percentage'] ?? 100) < 15) {
                $slugs[] = 'zebra';
            }
            // Se foi vitória do visitante e a % foi baixa
            elseif (!$isHomeWin && ($stats['away_win_percentage'] ?? 100) < 15) {
                $slugs[] = 'zebra';
            }
        }

        return $slugs;
    }

    private function award(Prediction $prediction, string $badgeSlug)
    {
        if (!$this->badges->has($badgeSlug)) return;
        $badge = $this->badges->get($badgeSlug);

        DB::table('user_badges')->insert([
            'user_id' => $prediction->user_id,
            'badge_id' => $badge->id,
            'league_id' => $prediction->league_id,
            'match_id' => $prediction->match_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Badge awarded: {$badge->name} to User {$prediction->user_id}");
    }

    private function revoke(Prediction $prediction, string $badgeSlug)
    {
        if (!$this->badges->has($badgeSlug)) return;
        $badge = $this->badges->get($badgeSlug);

        DB::table('user_badges')
            ->where('user_id', $prediction->user_id)
            ->where('badge_id', $badge->id)
            ->where('match_id', $prediction->match_id)
            ->delete();

        Log::info("Badge revoked: {$badge->name} from User {$prediction->user_id}");
    }
}
