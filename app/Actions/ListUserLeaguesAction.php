<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUserLeaguesAction
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $query = $user->leagues()->with(['competition', 'owner']);

        // Filtro por Competição
        if (isset($filters['competition_id'])) {
            $query->where('competition_id', $filters['competition_id']);
        }

        // Filtro por Nome
        if (isset($filters['name'])) {
            $query->where('name', 'ilike', '%' . $filters['name'] . '%');
        }

        // Filtro por Status
        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'finished') {
            $query->where('is_active', false);
        }

        $perPage = $filters['per_page'] ?? 20;
        $leagues = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Otimização: Buscar próximos jogos apenas para ligas ativas
        $competitionIds = $leagues->getCollection()
            ->where('is_active', true) // Filtra apenas ativas
            ->pluck('competition_id')
            ->unique()
            ->toArray();

        $nextMatches = [];
        if (!empty($competitionIds)) {
            $matches = FootballMatch::whereIn('competition_id', $competitionIds)
                ->where('utc_date', '>', now())
                ->whereIn('status', ['SCHEDULED', 'TIMED'])
                ->orderBy('competition_id')
                ->orderBy('utc_date', 'asc')
                ->distinct('competition_id')
                ->with(['homeTeam', 'awayTeam'])
                ->get()
                ->keyBy('competition_id');

            $nextMatches = $matches;
        }

        // Processa cada liga
        $leagues->getCollection()->each(function ($league) use ($user, $nextMatches) {
            if (!$league->is_active) {
                $league->pending_predictions_count = 0;
                $league->next_match = null;
                return;
            }

            // Anexa o próximo jogo
            $league->next_match = $nextMatches->get($league->competition_id);

            $league->pending_predictions_count = FootballMatch::where('competition_id', $league->competition_id)
                ->where('utc_date', '>', now())
                ->where('utc_date', '<=', now()->addDays(3))
                ->whereDoesntHave('predictions', function ($q) use ($user, $league) {
                    $q->where('user_id', $user->id)
                      ->where('league_id', $league->id);
                })
                ->count();
        });

        return $leagues;
    }
}
