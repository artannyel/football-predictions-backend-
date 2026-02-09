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

        if (!$match || $match->status !== 'FINISHED') {
            return;
        }

        $predictions = Prediction::where('match_id', $match->external_id)->get();

        foreach ($predictions as $prediction) {
            $result = $calculator->execute($prediction, $match);
            $newPoints = $result['points'];
            $newType = $result['type'];

            $oldPoints = $prediction->points_earned ?? 0;
            $oldType = $prediction->result_type;

            // Se nada mudou, pula
            if ($newPoints === $oldPoints && $newType === $oldType) {
                continue;
            }

            // Atualiza o palpite
            $prediction->points_earned = $newPoints;
            $prediction->result_type = $newType;
            $prediction->save();

            // Atualiza estatísticas nas ligas
            $this->updateUserLeagueStats($prediction->user_id, $match->competition_id, $oldPoints, $newPoints, $oldType, $newType);
        }
    }

    private function updateUserLeagueStats($userId, $competitionId, $oldPoints, $newPoints, $oldType, $newType)
    {
        $leagues = \App\Models\League::where('competition_id', $competitionId)
            ->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get();

        foreach ($leagues as $league) {
            $updates = [
                'points' => DB::raw("points + ($newPoints - $oldPoints)"),
            ];

            // Decrementa estatística antiga
            if ($oldType) {
                $col = $this->getTypeColumn($oldType);
                if ($col) {
                    $updates[$col] = DB::raw("$col - 1");
                }
                // Se tinha tipo antigo, significa que já foi contabilizado no total, então não mexe no total_predictions
                // A menos que estejamos reprocessando do zero, mas assumimos que oldType null = nunca processado
            } else {
                // Se não tinha tipo antigo, é um novo processamento
                $updates['total_predictions'] = DB::raw("total_predictions + 1");
            }

            // Incrementa estatística nova
            if ($newType) {
                $col = $this->getTypeColumn($newType);
                if ($col) {
                    $updates[$col] = DB::raw("$col + 1");
                }
            }

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
