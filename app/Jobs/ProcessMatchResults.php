<?php

namespace App\Jobs;

use App\Actions\CalculatePredictionPointsAction;
use App\Actions\GetMatchPredictionStatsAction;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use App\Services\BadgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessMatchResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $matchExternalId) {}

    public function handle(
        CalculatePredictionPointsAction $calculator,
        BadgeService $badgeService,
        GetMatchPredictionStatsAction $statsAction
    ): void
    {
        $match = FootballMatch::where('external_id', $this->matchExternalId)->first();

        if (!$match) {
            return;
        }

        $matchStats = [];
        if ($match->status === 'FINISHED') {
            $matchStats = $statsAction->execute($match->external_id);
        }

        $predictions = Prediction::where('match_id', $match->external_id)->get();

        // Agrupa predições por liga para otimizar a verificação de pódio
        $leaguesToUpdate = [];

        foreach ($predictions as $prediction) {
            $result = $calculator->execute($prediction, $match);
            $newPoints = $result['points'];
            $newType = $result['type'];

            $oldPoints = $prediction->points_earned ?? 0;
            $oldType = $prediction->result_type;

            $prediction->points_earned = $newPoints;
            $prediction->result_type = $newType;

            $badgeResult = ['awarded' => [], 'revoked' => []];
            if ($match->status === 'FINISHED') {
                $badgeResult = $badgeService->syncBadges($prediction, $match, $matchStats);
            }

            $this->updateUserLeagueStats($prediction->user_id, $prediction->league_id, $oldPoints, $newPoints, $oldType, $newType);

            // Marca a liga para verificação posterior
            $leaguesToUpdate[$prediction->league_id] = true;

            $milestoneResult = ['awarded' => [], 'revoked' => []];
            if ($match->status === 'FINISHED') {
                $totalPoints = DB::table('league_user')
                    ->where('user_id', $prediction->user_id)
                    ->where('league_id', $prediction->league_id)
                    ->value('points');

                if ($totalPoints !== null) {
                    $milestoneResult = $badgeService->checkMilestoneBadges($prediction->user_id, $prediction->league_id, $totalPoints);
                }
            }

            $allAwarded = array_merge($badgeResult['awarded'], $milestoneResult['awarded']);
            $allRevoked = array_merge($badgeResult['revoked'], $milestoneResult['revoked']);

            if ($match->status === 'FINISHED' && ($newPoints > 0 || !empty($allAwarded) || !empty($allRevoked))) {
                SendMatchResultNotification::dispatch(
                    $prediction->user_id,
                    $match->external_id,
                    $newPoints,
                    $newType,
                    $prediction->league_id,
                    $allAwarded,
                    $allRevoked
                );
            }

            if ($newPoints === $oldPoints && $newType === $oldType && empty($allAwarded) && empty($allRevoked)) {
                continue;
            }

            $prediction->save();
        }

        // Verifica pódio das ligas afetadas
        foreach (array_keys($leaguesToUpdate) as $leagueId) {
            $this->updateLeaguePodiumIfFinished($leagueId);
        }
    }

    private function updateUserLeagueStats($userId, $leagueId, $oldPoints, $newPoints, $oldType, $newType)
    {
        $league = \App\Models\League::find($leagueId);

        if (!$league) return;

        $updates = [];

        if ($newPoints !== $oldPoints) {
            $updates['points'] = DB::raw("points + ($newPoints - $oldPoints)");
        }

        if ($newType !== $oldType) {
            if ($oldType && $oldType !== 'PENDING') {
                $col = $this->getTypeColumn($oldType);
                if ($col) $updates[$col] = DB::raw("$col - 1");
            } else {
                if (is_null($oldType)) {
                    $updates['total_predictions'] = DB::raw("total_predictions + 1");
                }
            }

            if ($newType && $newType !== 'PENDING') {
                $col = $this->getTypeColumn($newType);
                if ($col) $updates[$col] = DB::raw("$col + 1");
            }
        }

        if (!empty($updates)) {
            $league->members()->updateExistingPivot($userId, $updates);
        }
    }

    private function updateLeaguePodiumIfFinished($leagueId)
    {
        $league = League::find($leagueId);

        // Só recalcula se a liga já estiver finalizada
        if (!$league || $league->is_active) {
            return;
        }

        $topMembers = $league->members()
            ->orderByPivot('points', 'desc')
            ->orderByPivot('exact_score_count', 'desc')
            ->orderByPivot('winner_diff_count', 'desc')
            ->orderByPivot('winner_goal_count', 'desc')
            ->orderByPivot('winner_only_count', 'desc')
            ->orderByPivot('error_count', 'asc')
            ->limit(3)
            ->get();

        $updateData = [
            'champion_id' => null,
            'runner_up_id' => null,
            'third_place_id' => null,
        ];

        if ($topMembers->isNotEmpty()) {
            $updateData['champion_id'] = $topMembers[0]->id;

            if (isset($topMembers[1])) {
                $updateData['runner_up_id'] = $topMembers[1]->id;
            }

            if (isset($topMembers[2])) {
                $updateData['third_place_id'] = $topMembers[2]->id;
            }
        }

        $league->update($updateData);
    }

    private function getTypeColumn($type)
    {
        return match ($type) {
            'EXACT_SCORE' => 'exact_score_count',
            'WINNER_DIFF' => 'winner_diff_count',
            'WINNER_GOAL' => 'winner_goal_count',
            'WINNER_ONLY' => 'winner_only_count',
            'ERROR' => 'error_count',
            default => null,
        };
    }
}
