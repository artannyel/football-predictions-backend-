<?php

namespace App\Jobs;

use App\Actions\CalculatePredictionPointsAction;
use App\Actions\GetMatchPredictionStatsAction;
use App\Models\FootballMatch;
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

            if ($match->status === 'FINISHED' && ($newPoints > 0 || !empty($badgeResult['awarded']) || !empty($badgeResult['revoked']))) {
                SendMatchResultNotification::dispatch(
                    $prediction->user_id,
                    $match->external_id,
                    $newPoints,
                    $newType,
                    $prediction->league_id,
                    $badgeResult['awarded'],
                    $badgeResult['revoked']
                );
            }

            if ($newPoints === $oldPoints && $newType === $oldType && empty($badgeResult['awarded']) && empty($badgeResult['revoked'])) {
                continue;
            }

            $prediction->save();

            $this->updateUserLeagueStats($prediction->user_id, $prediction->league_id, $oldPoints, $newPoints, $oldType, $newType);
        }
    }

    private function updateUserLeagueStats($userId, $leagueId, $oldPoints, $newPoints, $oldType, $newType)
    {
        $league = \App\Models\League::find($leagueId);

        if (!$league) return;

        $updates = [];

        // Só atualiza pontos se mudou
        if ($newPoints !== $oldPoints) {
            $updates['points'] = DB::raw("points + ($newPoints - $oldPoints)");
        }

        // Só atualiza contadores se o tipo mudou
        if ($newType !== $oldType) {
            // Decrementa antigo
            if ($oldType && $oldType !== 'PENDING') {
                $col = $this->getTypeColumn($oldType);
                if ($col) $updates[$col] = DB::raw("$col - 1");
            } else {
                // Se não tinha tipo antes (primeira vez), incrementa total
                if (is_null($oldType)) {
                    $updates['total_predictions'] = DB::raw("total_predictions + 1");
                }
            }

            // Incrementa novo
            if ($newType && $newType !== 'PENDING') {
                $col = $this->getTypeColumn($newType);
                if ($col) $updates[$col] = DB::raw("$col + 1");
            }
        }

        if (!empty($updates)) {
            $league->members()->updateExistingPivot($userId, $updates);
        }
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
