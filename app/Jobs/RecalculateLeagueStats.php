<?php

namespace App\Jobs;

use App\Actions\CalculatePredictionPointsAction;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateLeagueStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $leagueId) {}

    public function handle(CalculatePredictionPointsAction $calculator): void
    {
        $league = League::find($this->leagueId);
        if (!$league) return;

        Log::channel('recalculation')->info("Recalculating stats for League {$league->id} ({$league->name})...");

        // 1. Zerar estatísticas desta liga
        DB::table('league_user')
            ->where('league_id', $league->id)
            ->update([
                'points' => 0,
                'exact_score_count' => 0,
                'winner_diff_count' => 0,
                'winner_goal_count' => 0,
                'winner_only_count' => 0,
                'error_count' => 0,
                'total_predictions' => 0,
            ]);

        // 2. Buscar jogos finalizados da competição desta liga
        $matches = FootballMatch::where('competition_id', $league->competition_id)
            ->where('status', 'FINISHED')
            ->get();

        // 3. Processar palpites
        foreach ($matches as $match) {
            $predictions = Prediction::where('match_id', $match->external_id)
                ->where('league_id', $league->id)
                ->get();

            foreach ($predictions as $prediction) {
                $result = $calculator->execute($prediction, $match);
                $points = $result['points'];
                $type = $result['type'];

                // Atualiza o palpite (garantia)
                if ($prediction->points_earned !== $points || $prediction->result_type !== $type) {
                    $prediction->points_earned = $points;
                    $prediction->result_type = $type;
                    $prediction->saveQuietly();
                }

                $this->incrementStats($prediction->user_id, $league->id, $points, $type);
            }
        }

        // 4. Atualizar total_predictions (incluindo não finalizados)
        $totals = DB::table('predictions')
            ->where('league_id', $league->id)
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->get();

        foreach ($totals as $stat) {
            DB::table('league_user')
                ->where('user_id', $stat->user_id)
                ->where('league_id', $league->id)
                ->update(['total_predictions' => $stat->total]);
        }

        Log::channel('recalculation')->info("League {$league->id} recalculated.");
    }

    private function incrementStats($userId, $leagueId, $points, $type)
    {
        $updates = [
            'points' => DB::raw("points + $points"),
        ];

        $col = $this->getTypeColumn($type);
        if ($col) {
            $updates[$col] = DB::raw("$col + 1");
        }

        DB::table('league_user')
            ->where('user_id', $userId)
            ->where('league_id', $leagueId)
            ->update($updates);
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
