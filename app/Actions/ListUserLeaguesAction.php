<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUserLeaguesAction
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        // Adicionado withCount('members') para evitar N+1
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

        $competitionIds = $leagues->getCollection()
            ->where('is_active', true)
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

        $leagues->getCollection()->each(function ($league) use ($user, $nextMatches) {
            if (!$league->is_active) {
                $league->pending_predictions_count = 0;
                $league->next_match = null;
                return;
            }

            $league->next_match = $nextMatches->get($league->competition_id);

            $league->pending_predictions_count = FootballMatch::where('competition_id', $league->competition_id)
                ->where('utc_date', '>', now())
                ->where('utc_date', '<=', now()->addDays(3))
                ->whereNot('status', 'POSTPONED')
                ->whereDoesntHave('predictions', function ($q) use ($user, $league) {
                    $q->where('user_id', $user->id)
                      ->where('league_id', $league->id);
                })
                ->count();
        });

        return $leagues;
    }
}
