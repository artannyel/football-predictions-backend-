<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

class UserStatsService
{
    /**
     * Processa as estatísticas globais para um usuário e jogo.
     * Deve ser chamado APÓS o palpite ser salvo com os novos pontos.
     */
    public function processPrediction(string $userId, FootballMatch $match): void
    {
        // 1. Busca o melhor palpite do usuário para este jogo (considerando todas as ligas)
        // Filtra apenas palpites de ligas válidas (Anti-Farm)
        $bestPrediction = Prediction::query()
            ->join('league_user', function ($join) {
                $join->on('predictions.league_id', '=', 'league_user.league_id')
                     ->where('league_user.user_id', '=', DB::raw('predictions.user_id')); // Join com o próprio usuário para pegar created_at dele? Não.
                     // Precisamos validar a liga, não o usuário.
            })
            ->where('predictions.user_id', $userId)
            ->where('predictions.match_id', $match->external_id)
            ->whereExists(function ($query) use ($match) {
                // Regra Anti-Farm: A liga deve ter um 2º membro que entrou ANTES do jogo
                $query->select(DB::raw(1))
                    ->from('league_user as lu')
                    ->whereColumn('lu.league_id', 'predictions.league_id')
                    ->orderBy('lu.created_at', 'asc')
                    ->offset(1) // Pula o 1º
                    ->limit(1)
                    ->where('lu.created_at', '<', $match->utc_date);
            })
            ->orderBy('predictions.points_earned', 'desc')
            ->select('predictions.*')
            ->first();

        // Se não tiver nenhum palpite válido (todos em ligas farm ou sem palpites), zera ou remove
        if (!$bestPrediction) {
            // Se existia registro, precisa remover e estornar os pontos
            $this->removeStats($userId, $match);
            return;
        }

        // 2. Compara com o registro atual em user_match_stats
        $currentStat = DB::table('user_match_stats')
            ->where('user_id', $userId)
            ->where('match_id', $match->external_id)
            ->first();

        $newPoints = $bestPrediction->points_earned;
        $newType = $bestPrediction->result_type;

        $oldPoints = $currentStat->points ?? 0;
        $oldType = $currentStat->result_type ?? null;

        // Se nada mudou, sai
        if ($currentStat && $newPoints === $oldPoints && $newType === $oldType) {
            return;
        }

        // 3. Atualiza user_match_stats
        DB::table('user_match_stats')->updateOrInsert(
            ['user_id' => $userId, 'match_id' => $match->external_id],
            [
                'points' => $newPoints,
                'result_type' => $newType,
                'match_date' => $match->utc_date,
                'updated_at' => now(),
            ]
        );

        // 4. Propaga Delta
        $deltaPoints = $newPoints - $oldPoints;
        $isNewMatch = !$currentStat;

        $periods = [
            'GLOBAL',
            $match->utc_date->format('Y'),
            $match->utc_date->format('Y-m'),
        ];

        foreach ($periods as $period) {
            $this->updateAggregateStats($userId, $period, $deltaPoints, $oldType, $newType, $isNewMatch);
        }
    }

    private function removeStats($userId, FootballMatch $match)
    {
        $currentStat = DB::table('user_match_stats')
            ->where('user_id', $userId)
            ->where('match_id', $match->external_id)
            ->first();

        if (!$currentStat) return;

        // Estorna tudo
        $deltaPoints = -($currentStat->points);
        $oldType = $currentStat->result_type;

        DB::table('user_match_stats')->where('id', $currentStat->id)->delete();

        $periods = [
            'GLOBAL',
            $match->utc_date->format('Y'),
            $match->utc_date->format('Y-m'),
        ];

        foreach ($periods as $period) {
            // isNewMatch = false (estamos removendo), mas precisamos decrementar total_predictions
            // A função updateAggregateStats incrementa se isNewMatch=true.
            // Para decrementar, precisamos de uma lógica de "remover".
            // Vamos adaptar updateAggregateStats ou fazer manual aqui.

            // Manual é mais seguro para remoção
            $updates = [
                'points' => DB::raw("points + $deltaPoints"),
                'total_predictions' => DB::raw("total_predictions - 1"),
            ];

            if ($oldType) {
                $col = $this->getTypeColumn($oldType);
                if ($col) $updates[$col] = DB::raw("$col - 1");
            }

            DB::table('user_stats')
                ->where('user_id', $userId)
                ->where('period', $period)
                ->update($updates);
        }
    }

    private function updateAggregateStats($userId, $period, $deltaPoints, $oldType, $newType, $isNewMatch)
    {
        $updates = [];

        if ($deltaPoints != 0) {
            $updates['points'] = DB::raw("points + $deltaPoints");
        }

        if ($isNewMatch) {
            $updates['total_predictions'] = DB::raw("total_predictions + 1");
        }

        if ($oldType && $oldType !== $newType) {
            $col = $this->getTypeColumn($oldType);
            if ($col) $updates[$col] = DB::raw("$col - 1");
        }

        if ($newType && ($isNewMatch || $oldType !== $newType)) {
            $col = $this->getTypeColumn($newType);
            if ($col) $updates[$col] = DB::raw("$col + 1");
        }

        if (empty($updates)) return;

        $affected = DB::table('user_stats')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->update($updates);

        if ($affected === 0) {
            $initialData = [
                'user_id' => $userId,
                'period' => $period,
                'points' => $deltaPoints,
                'total_predictions' => $isNewMatch ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($newType) {
                $col = $this->getTypeColumn($newType);
                if ($col) $initialData[$col] = 1;
            }

            DB::table('user_stats')->insertOrIgnore($initialData);
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
