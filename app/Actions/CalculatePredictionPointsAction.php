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
        if ($match->utc_date > now() && $match->status === 'SCHEDULED') {
            return ['points' => 0, 'type' => 'PENDING'];
        }

        $predHome = $prediction->home_score;
        $predAway = $prediction->away_score;

        $realHome = $match->score_fulltime_home;
        $realAway = $match->score_fulltime_away;

        if (is_null($realHome) || is_null($realAway)) {
            $realHome = $match->score_halftime_home;
            $realAway = $match->score_halftime_away;

            if (is_null($realHome) || is_null($realAway)) {
                 return ['points' => 0, 'type' => 'PENDING'];
            }
        }

        $points = 0;
        $type = 'ERROR';

        // 1. Placar Exato (7 pontos)
        if ($predHome === $realHome && $predAway === $realAway) {
            $points = 7;
            $type = 'EXACT_SCORE';
        } else {
            $predResult = $this->getResult($predHome, $predAway);
            $realResult = $this->getResult($realHome, $realAway);

            if ($predResult === $realResult) {
                // 2. Acertou Vencedor + Saldo de Gols (5 pontos)
                $predDiff = $predHome - $predAway;
                $realDiff = $realHome - $realAway;

                if ($predDiff === $realDiff) {
                    $points = 5;
                    $type = 'WINNER_DIFF';
                }
                // 3. Acertou Vencedor + Gols de um dos times (3 pontos)
                elseif ($predHome === $realHome || $predAway === $realAway) {
                    $points = 3;
                    $type = 'WINNER_GOAL';
                }
                // 4. Apenas acertou o vencedor (1 ponto)
                else {
                    $points = 1;
                    $type = 'WINNER_ONLY';
                }
            }
        }

        // Aplica Power-Up (x2)
        if ($prediction->powerup_used === 'x2') {
            $points *= 2;
        }

        return ['points' => $points, 'type' => $type];
    }

    private function getResult($home, $away)
    {
        if ($home > $away) return 'HOME_TEAM';
        if ($away > $home) return 'AWAY_TEAM';
        return 'DRAW';
    }
}
