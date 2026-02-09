<?php

use App\Actions\CalculatePredictionPointsAction;
use App\Models\League;
use App\Models\Prediction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $calculator = new CalculatePredictionPointsAction();

        // 1. Recalcular Palpites
        $predictions = Prediction::with('match')->get();

        foreach ($predictions as $prediction) {
            if ($prediction->match && $prediction->match->status === 'FINISHED') {
                $result = $calculator->execute($prediction, $prediction->match);

                DB::table('predictions')
                    ->where('id', $prediction->id)
                    ->update([
                        'points_earned' => $result['points'],
                        'result_type' => $result['type']
                    ]);
            }
        }

        // 2. Zerar estatísticas das Ligas
        DB::table('league_user')->update([
            'points' => 0,
            'exact_score_count' => 0,
            'winner_diff_count' => 0,
            'winner_goal_count' => 0,
            'winner_only_count' => 0,
            'error_count' => 0,
            'total_predictions' => 0,
        ]);

        // 3. Recalcular Estatísticas
        $leagues = League::all();

        foreach ($leagues as $league) {
            $members = $league->members;

            foreach ($members as $member) {
                // Busca palpites válidos desse usuário nessa competição
                $userPredictions = Prediction::where('user_id', $member->id)
                    ->whereHas('match', function ($q) use ($league) {
                        $q->where('competition_id', $league->competition_id)
                          ->where('status', 'FINISHED');
                    })
                    ->get();

                $stats = [
                    'points' => 0,
                    'exact_score_count' => 0,
                    'winner_diff_count' => 0,
                    'winner_goal_count' => 0,
                    'winner_only_count' => 0,
                    'error_count' => 0,
                    'total_predictions' => $userPredictions->count(),
                ];

                foreach ($userPredictions as $pred) {
                    $stats['points'] += $pred->points_earned;

                    switch ($pred->result_type) {
                        case 'EXACT_SCORE': $stats['exact_score_count']++; break;
                        case 'WINNER_DIFF': $stats['winner_diff_count']++; break;
                        case 'WINNER_GOAL': $stats['winner_goal_count']++; break;
                        case 'WINNER_ONLY': $stats['winner_only_count']++; break;
                        case 'ERROR': $stats['error_count']++; break;
                    }
                }

                $league->members()->updateExistingPivot($member->id, $stats);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não faz nada
    }
};
//🏆 Sistema de Pontuação
//Ganhe pontos acertando os resultados dos jogos! Veja como funciona:
//🎯 Placar Exato: 5 Pontos Acerte o placar exato da partida. Exemplo: Você palpitou 2x1 e o jogo terminou 2x1.
//✅ Vencedor + Saldo de Gols: 3 Pontos Acerte quem venceu (ou se deu empate) e a diferença exata de gols, mas errando o placar. Exemplo: Você palpitou 3x1 (saldo +2) e o jogo terminou 2x0 (saldo +2). Exemplo: Você palpitou 1x1 (empate) e o jogo terminou 2x2 (empate).
//🏅 Apenas Vencedor: 1 Ponto Acerte apenas quem venceu a partida, sem acertar o saldo de gols ou placar. Exemplo: Você palpitou 2x1 e o jogo terminou 1x0.
//❌ Erro: 0 Pontos Se você não acertar o vencedor nem o empate.
