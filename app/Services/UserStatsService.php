<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Prediction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserStatsService
{
    protected string $timezone = 'America/Sao_Paulo';

    /**
     * Processa as estatísticas globais para um usuário e jogo.
     * Deve ser chamado APÓS o palpite ser salvo com os novos pontos.
     */
    public function processPrediction(string $userId, FootballMatch $match): void
    {
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/user_stats.log'),
        ]);

        // 1. Busca o melhor palpite
        $bestPrediction = Prediction::query()
            ->join('league_user', function ($join) {
                $join->on('predictions.league_id', '=', 'league_user.league_id')
                     ->where('league_user.user_id', '=', DB::raw('predictions.user_id'));
            })
            ->where('predictions.user_id', $userId)
            ->where('predictions.match_id', $match->external_id)
            ->whereExists(function ($query) use ($match) {
                $query->select(DB::raw(1))
                    ->from('league_user as lu')
                    ->whereColumn('lu.league_id', 'predictions.league_id')
                    ->orderBy('lu.created_at', 'asc')
                    ->offset(1)
                    ->limit(1)
                    ->where('lu.created_at', '<', $match->utc_date);
            })
            ->orderBy('predictions.points_earned', 'desc')
            ->select('predictions.*')
            ->first();

        if (!$bestPrediction) {
            $logger->info("No valid prediction found for User {$userId} Match {$match->external_id}. Removing stats.");
            $this->removeStats($userId, $match, $logger);
            return;
        }

        // 2. Compara com o registro atual
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

        $logger->info("Processing User {$userId} Match {$match->external_id}: Old={$oldPoints} ({$oldType}) -> New={$newPoints} ({$newType})");

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

        // 4. Calcula Períodos
        // Usa timezone BRT para definir o mês/ano
        $newDate = Carbon::parse($match->utc_date)->setTimezone($this->timezone);
        $newPeriods = [
            'GLOBAL',
            $newDate->format('Y'),
            $newDate->format('Y-m'),
        ];

        $oldPeriods = [];
        if ($currentStat && $currentStat->match_date) {
            $oldDate = Carbon::parse($currentStat->match_date)->setTimezone($this->timezone);
            $oldPeriods = [
                'GLOBAL',
                $oldDate->format('Y'),
                $oldDate->format('Y-m'),
            ];
        }

        // 5. Aplica Atualizações
        $isNewMatch = !$currentStat;

        // Se os períodos mudaram (ex: mudou de mês), precisamos tratar separado
        if (!$isNewMatch && ($newPeriods !== $oldPeriods)) {
            $logger->info("Match changed period! Old: " . json_encode($oldPeriods) . " New: " . json_encode($newPeriods));

            // Remove do período antigo
            foreach ($oldPeriods as $period) {
                // Se o período ainda existe no novo (ex: GLOBAL), aplica delta. Se não, remove tudo.
                if (in_array($period, $newPeriods)) {
                    // Período comum (ex: GLOBAL): Aplica Delta
                    $this->updateAggregateStats($userId, $period, $newPoints - $oldPoints, $oldType, $newType, false, $logger);
                } else {
                    // Período exclusivo antigo (ex: Mês Passado): Remove tudo
                    $this->revertStats($userId, $period, $oldPoints, $oldType, $logger);
                }
            }

            // Adiciona no período novo
            foreach ($newPeriods as $period) {
                if (!in_array($period, $oldPeriods)) {
                    // Período exclusivo novo (ex: Mês Novo): Adiciona tudo
                    $this->addStats($userId, $period, $newPoints, $newType, $logger);
                }
            }
        } else {
            // Períodos iguais (caso comum): Aplica Delta em todos
            $deltaPoints = $newPoints - $oldPoints;
            foreach ($newPeriods as $period) {
                $this->updateAggregateStats($userId, $period, $deltaPoints, $oldType, $newType, $isNewMatch, $logger);
            }
        }
    }

    private function removeStats($userId, FootballMatch $match, $logger)
    {
        $currentStat = DB::table('user_match_stats')
            ->where('user_id', $userId)
            ->where('match_id', $match->external_id)
            ->first();

        if (!$currentStat) return;

        $oldPoints = $currentStat->points;
        $oldType = $currentStat->result_type;

        $logger->info("Removing stats for User {$userId} Match {$match->external_id}: Reverting {$oldPoints} points.");

        DB::table('user_match_stats')->where('id', $currentStat->id)->delete();

        $date = Carbon::parse($currentStat->match_date ?? $match->utc_date)->setTimezone($this->timezone);
        $periods = [
            'GLOBAL',
            $date->format('Y'),
            $date->format('Y-m'),
        ];

        foreach ($periods as $period) {
            $this->revertStats($userId, $period, $oldPoints, $oldType, $logger);
        }
    }

    // Função auxiliar para remover estatísticas de um período (Estorno total)
    private function revertStats($userId, $period, $points, $type, $logger)
    {
        $updates = [
            'points' => DB::raw("points - $points"),
            'total_predictions' => DB::raw("total_predictions - 1"),
        ];

        if ($type) {
            $col = $this->getTypeColumn($type);
            if ($col) $updates[$col] = DB::raw("$col - 1");
        }

        $logger->info("Reverting [{$period}] for User {$userId}: -{$points} pts");

        DB::table('user_stats')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->update($updates);
    }

    // Função auxiliar para adicionar estatísticas em um período (Inserção total)
    private function addStats($userId, $period, $points, $type, $logger)
    {
        $updates = [
            'points' => DB::raw("points + $points"),
            'total_predictions' => DB::raw("total_predictions + 1"),
        ];

        if ($type) {
            $col = $this->getTypeColumn($type);
            if ($col) $updates[$col] = DB::raw("$col + 1");
        }

        $logger->info("Adding to [{$period}] for User {$userId}: +{$points} pts");

        $affected = DB::table('user_stats')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->update($updates);

        if ($affected === 0) {
            $initialData = [
                'user_id' => $userId,
                'period' => $period,
                'points' => $points,
                'total_predictions' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($type) {
                $col = $this->getTypeColumn($type);
                if ($col) $initialData[$col] = 1;
            }

            DB::table('user_stats')->insertOrIgnore($initialData);
        }
    }

    private function updateAggregateStats($userId, $period, $deltaPoints, $oldType, $newType, $isNewMatch, $logger)
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

        $logger->info("Updating Aggregate [{$period}] for User {$userId}: Delta={$deltaPoints}");

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
