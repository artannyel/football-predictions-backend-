<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListOtherUserPredictionsAction
{
    public function execute(string $targetUserId, string $leagueId, string $currentUserId, int $perPage = 20): LengthAwarePaginator
    {
        $league = League::findOrFail($leagueId);

        // Busca jogos da competição que já aconteceram E que são posteriores à criação da liga
        $paginator = FootballMatch::where('competition_id', $league->competition_id)
            ->where('utc_date', '<=', now())
            ->where('utc_date', '>=', $league->created_at)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('utc_date', 'desc')
            ->paginate($perPage);

        $matchIds = $paginator->getCollection()->pluck('external_id')->toArray();

        if (empty($matchIds)) {
            return $paginator;
        }

        // Busca palpites do Target com Badges
        $targetPredictions = Prediction::where('user_id', $targetUserId)
            ->where('league_id', $leagueId)
            ->whereIn('match_id', $matchIds)
            ->with('badges')
            ->get()
            ->keyBy('match_id');

        // Busca palpites do Current User
        $myPredictions = Prediction::where('user_id', $currentUserId)
            ->where('league_id', $leagueId)
            ->whereIn('match_id', $matchIds)
            ->with('badges')
            ->get()
            ->keyBy('match_id');

        $paginator->getCollection()->transform(function ($match) use ($targetPredictions, $myPredictions) {
            $targetPred = $targetPredictions->get($match->external_id);
            $myPred = $myPredictions->get($match->external_id);

            if ($targetPred) {
                $targetPred->setRelation('match', $match);
                $targetPred->my_prediction = $myPred;
                return $targetPred;
            }

            $fakePrediction = new Prediction([
                'id' => null,
                'match_id' => $match->external_id,
                'home_score' => null,
                'away_score' => null,
                'points_earned' => null,
                'result_type' => null,
                'created_at' => null,
            ]);

            $fakePrediction->setRelation('match', $match);
            $fakePrediction->my_prediction = $myPred;

            return $fakePrediction;
        });

        return $paginator;
    }
}
