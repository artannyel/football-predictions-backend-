<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\Prediction;

class CalculatePredictionPointsAction
{
    /**
     * Returns array with points and result type
     * @return array{points: int, type: string}
     */
    public function execute(Prediction $prediction, FootballMatch $match): array
    {
        // Se o jogo ainda não começou (SCHEDULED, TIMED), retorna 0
        if ($match->utc_date > now() && $match->status === 'SCHEDULED') {
            return ['points' => 0, 'type' => 'PENDING'];
        }

        $predHome = $prediction->home_score;
        $predAway = $prediction->away_score;

        // Usamos o placar Full Time. Se estiver em andamento, a API geralmente preenche o fullTime com o placar atual.
        $realHome = $match->score_fulltime_home;
        $realAway = $match->score_fulltime_away;

        // Se não tiver placar ainda (ex: jogo começou mas API não mandou 0-0), assume 0-0 ou retorna erro
        if (is_null($realHome) || is_null($realAway)) {
            // Tenta usar halftime se fulltime for null (raro)
            $realHome = $match->score_halftime_home;
            $realAway = $match->score_halftime_away;

            if (is_null($realHome) || is_null($realAway)) {
                 return ['points' => 0, 'type' => 'PENDING'];
            }
        }

        // 1. Placar Exato (7 pontos)
        if ($predHome === $realHome && $predAway === $realAway) {
            return ['points' => 7, 'type' => 'EXACT_SCORE'];
        }

        $predResult = $this->getResult($predHome, $predAway);
        $realResult = $this->getResult($realHome, $realAway);

        // Se não acertou o resultado (Vencedor/Empate)
        if ($predResult !== $realResult) {
            return ['points' => 0, 'type' => 'ERROR'];
        }

        // 2. Acertou Vencedor + Saldo de Gols (5 pontos)
        $predDiff = $predHome - $predAway;
        $realDiff = $realHome - $realAway;

        if ($predDiff === $realDiff) {
            return ['points' => 5, 'type' => 'WINNER_DIFF'];
        }

        // 3. Acertou Vencedor + Gols de um dos times (3 pontos)
        if ($predHome === $realHome || $predAway === $realAway) {
            return ['points' => 3, 'type' => 'WINNER_GOAL'];
        }

        // 4. Apenas acertou o vencedor (1 ponto)
        return ['points' => 1, 'type' => 'WINNER_ONLY'];
    }

    private function getResult($home, $away)
    {
        if ($home > $away) return 'HOME_TEAM';
        if ($away > $home) return 'AWAY_TEAM';
        return 'DRAW';
    }
}
