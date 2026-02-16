<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeService
{
    protected $badges;

    public function __construct()
    {
        $this->badges = Badge::all()->keyBy('slug');
    }

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

    public function syncBadgesBatch(Collection $predictions, FootballMatch $match, array $matchStats = []): void
    {
        if ($predictions->isEmpty()) return;

        $userIds = $predictions->pluck('user_id')->toArray();
        $matchId = $match->external_id;

        $existingRecords = DB::table('user_badges')
            ->where('match_id', $matchId)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $toInsert = [];
        $idsToDelete = [];
        $now = now();

        foreach ($predictions as $prediction) {
            $deservedSlugs = $this->calculateDeservedBadges($prediction, $match, $matchStats);

            $userExistingBadges = $existingRecords->get($prediction->user_id, collect());
            $existingBadgeIds = $userExistingBadges->pluck('badge_id')->toArray();

            $deservedBadgeIds = [];
            foreach ($deservedSlugs as $slug) {
                if ($this->badges->has($slug)) {
                    $deservedBadgeIds[] = $this->badges->get($slug)->id;
                }
            }

            foreach ($deservedBadgeIds as $badgeId) {
                if (!in_array($badgeId, $existingBadgeIds)) {
                    $toInsert[] = [
                        'user_id' => $prediction->user_id,
                        'badge_id' => $badgeId,
                        'league_id' => $prediction->league_id,
                        'match_id' => $matchId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach ($userExistingBadges as $record) {
                if (!in_array($record->badge_id, $deservedBadgeIds)) {
                    $idsToDelete[] = $record->id;
                }
            }
        }

        if (!empty($idsToDelete)) {
            DB::table('user_badges')->whereIn('id', $idsToDelete)->delete();
            Log::channel('recalculation')->info("Batch revoked " . count($idsToDelete) . " badges for match {$matchId}");
        }

        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table('user_badges')->insert($chunk);
            }
            Log::channel('recalculation')->info("Batch awarded " . count($toInsert) . " badges for match {$matchId}");
        }
    }

    /**
     * Processa medalhas de marco em lote para membros de uma liga.
     * @param Collection $members Collection de objetos com {user_id, points}
     * @param string $leagueId
     */
    public function syncMilestoneBadgesBatch(Collection $members, string $leagueId): void
    {
        if ($members->isEmpty()) return;

        $userIds = $members->pluck('user_id')->toArray();

        $milestones = [
            50 => 'points_50',
            200 => 'points_200',
            500 => 'points_500',
            1000 => 'points_1000',
        ];

        // IDs das medalhas de marco
        $milestoneSlugs = array_values($milestones);
        $milestoneBadgeIds = $this->badges->whereIn('slug', $milestoneSlugs)->pluck('id')->toArray();

        // Carrega existentes
        $existingRecords = DB::table('user_badges')
            ->where('league_id', $leagueId)
            ->whereIn('user_id', $userIds)
            ->whereIn('badge_id', $milestoneBadgeIds)
            ->get()
            ->groupBy('user_id');

        $toInsert = [];
        $idsToDelete = [];
        $now = now();

        foreach ($members as $member) {
            $userExistingBadges = $existingRecords->get($member->user_id, collect());
            $existingBadgeIds = $userExistingBadges->pluck('badge_id')->toArray();

            $deservedBadgeIds = [];

            foreach ($milestones as $points => $slug) {
                if ($member->points >= $points && $this->badges->has($slug)) {
                    $deservedBadgeIds[] = $this->badges->get($slug)->id;
                }
            }

            // Insert
            foreach ($deservedBadgeIds as $badgeId) {
                if (!in_array($badgeId, $existingBadgeIds)) {
                    $toInsert[] = [
                        'user_id' => $member->user_id,
                        'badge_id' => $badgeId,
                        'league_id' => $leagueId,
                        'match_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Delete (Revoke)
            foreach ($userExistingBadges as $record) {
                if (!in_array($record->badge_id, $deservedBadgeIds)) {
                    $idsToDelete[] = $record->id;
                }
            }
        }

        if (!empty($idsToDelete)) {
            DB::table('user_badges')->whereIn('id', $idsToDelete)->delete();
            Log::channel('recalculation')->info("Batch revoked " . count($idsToDelete) . " milestone badges for League {$leagueId}");
        }

        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table('user_badges')->insert($chunk);
            }
            Log::channel('recalculation')->info("Batch awarded " . count($toInsert) . " milestone badges for League {$leagueId}");
        }
    }

    public function checkMilestoneBadges(string $userId, string $leagueId, int $totalPoints): array
    {
        $milestones = [
            50 => 'points_50',
            200 => 'points_200',
            500 => 'points_500',
            1000 => 'points_1000',
        ];

        $awarded = [];
        $revoked = [];

        $milestoneSlugs = array_values($milestones);
        $milestoneBadgeIds = $this->badges->whereIn('slug', $milestoneSlugs)->pluck('id')->toArray();

        $existingBadgeIds = DB::table('user_badges')
            ->where('user_id', $userId)
            ->where('league_id', $leagueId)
            ->whereIn('badge_id', $milestoneBadgeIds)
            ->pluck('badge_id')
            ->toArray();

        foreach ($milestones as $points => $slug) {
            if (!$this->badges->has($slug)) continue;
            $badge = $this->badges->get($slug);
            $hasBadge = in_array($badge->id, $existingBadgeIds);

            if ($totalPoints >= $points) {
                if (!$hasBadge) {
                    DB::table('user_badges')->insert([
                        'user_id' => $userId,
                        'badge_id' => $badge->id,
                        'league_id' => $leagueId,
                        'match_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $awarded[] = $badge;
                    Log::info("Milestone Badge awarded: {$badge->name} to User {$userId} in League {$leagueId}");
                }
            } else {
                if ($hasBadge) {
                    DB::table('user_badges')
                        ->where('user_id', $userId)
                        ->where('league_id', $leagueId)
                        ->where('badge_id', $badge->id)
                        ->delete();

                    $revoked[] = $badge;
                    Log::info("Milestone Badge revoked: {$badge->name} from User {$userId} in League {$leagueId}");
                }
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

        if ($prediction->result_type === 'EXACT_SCORE') {
            $slugs[] = 'sniper';
        }

        if ($match->score_fulltime_home === $match->score_fulltime_away) {
            $slugs[] = 'ousado';
        }

        if (!empty($stats) && $match->score_fulltime_home !== $match->score_fulltime_away) {
            $isHomeWin = $match->score_fulltime_home > $match->score_fulltime_away;

            if ($isHomeWin && ($stats['home_win_percentage'] ?? 100) < 15) {
                $slugs[] = 'zebra';
            }
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
