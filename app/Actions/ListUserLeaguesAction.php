<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUserLeaguesAction
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $query = $user->leagues()->with(['competition', 'owner'])->withCount('members');

        if (isset($filters['competition_id'])) {
            $query->where('competition_id', $filters['competition_id']);
        }

        if (isset($filters['name'])) {
            $query->where('name', 'ilike', '%' . $filters['name'] . '%');
        }

        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'finished') {
            $query->where('is_active', false);
        }

        $perPage = $filters['per_page'] ?? 20;
        $leagues = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // IDs das competições das ligas listadas
        $competitionIds = $leagues->getCollection()
            ->where('is_active', true)
            ->pluck('competition_id')
            ->unique()
            ->toArray();

        if (empty($competitionIds)) {
            return $leagues;
        }

        // 1. Buscar jogos futuros (próximos 3 dias) para essas competições
        // Carregamos homeTeam e awayTeam para o 'next_match'
        $matches = FootballMatch::whereIn('competition_id', $competitionIds)
            ->where('utc_date', '>', now())
            ->where('utc_date', '<=', now()->addDays(3))
            ->whereNot('status', 'POSTPONED')
            ->orderBy('utc_date', 'asc')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        // Agrupa jogos por competição para contagem e next_match
        $matchesByCompetition = $matches->groupBy('competition_id');

        // IDs de todos os jogos relevantes
        $matchIds = $matches->pluck('external_id')->toArray();

        // 2. Buscar palpites do usuário para esses jogos
        // Precisamos saber em qual liga foi o palpite
        $userPredictions = Prediction::where('user_id', $user->id)
            ->whereIn('match_id', $matchIds)
            ->whereIn('league_id', $leagues->pluck('id')->toArray())
            ->get()
            ->groupBy('league_id'); // Agrupa por liga

        // 3. Processar cada liga
        $leagues->getCollection()->each(function ($league) use ($matchesByCompetition, $userPredictions) {
            if (!$league->is_active) {
                $league->pending_predictions_count = 0;
                $league->next_match = null;
                return;
            }

            // Jogos disponíveis para esta competição
            $compMatches = $matchesByCompetition->get($league->competition_id, collect());

            // Próximo jogo (o primeiro da lista ordenada)
            $league->next_match = $compMatches->first();

            // Total de jogos disponíveis
            $totalMatchesCount = $compMatches->count();

            // Palpites feitos nesta liga para esses jogos
            // Filtramos os palpites para contar apenas os que batem com os jogos da janela de 3 dias
            $myPredictionsInWindow = $userPredictions->get($league->id, collect())
                ->whereIn('match_id', $compMatches->pluck('external_id'))
                ->count();

            // Pendentes = Total - Feitos
            $league->pending_predictions_count = max(0, $totalMatchesCount - $myPredictionsInWindow);
        });

        return $leagues;
    }
}
