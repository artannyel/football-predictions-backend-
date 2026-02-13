<?php

namespace App\Jobs;

use App\Actions\CalculatePredictionPointsAction;
use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMatchResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $matchExternalId) {}

    public function handle(CalculatePredictionPointsAction $calculator): void
    {
        $match = FootballMatch::where('external_id', $this->matchExternalId)->first();

        if (!$match) {
            return;
        }

        $predictions = Prediction::where('match_id', $match->external_id)->get();

        foreach ($predictions as $prediction) {
            $result = $calculator->execute($prediction, $match);
            $newPoints = $result['points'];
            $newType = $result['type'];

            $oldPoints = $prediction->points_earned ?? 0;
            $oldType = $prediction->result_type;

            // Envia notificação apenas se o jogo terminou e o usuário ganhou pontos
            // E se houve mudança de pontos (para não notificar repetido se reprocessar)
            if ($match->status === 'FINISHED' && $newPoints > 0) {
                SendMatchResultNotification::dispatch(
                    $prediction->user_id,
                    $match->external_id,
                    $newPoints,
                    $newType
                );
            }

            if ($newPoints === $oldPoints && $newType === $oldType) {
                continue;
            }

            $prediction->points_earned = $newPoints;
            $prediction->result_type = $newType;
            $prediction->save();

            $this->updateUserLeagueStats($prediction->user_id, $prediction->league_id, $oldPoints, $newPoints, $oldType, $newType);
        }
    }

    private function updateUserLeagueStats($userId, $leagueId, $oldPoints, $newPoints, $oldType, $newType)
    {
        $league = \App\Models\League::find($leagueId);

        if (!$league) return;

        $updates = [
            'points' => DB::raw("points + ($newPoints - $oldPoints)"),
        ];

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

        $league->members()->updateExistingPivot($userId, $updates);
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
