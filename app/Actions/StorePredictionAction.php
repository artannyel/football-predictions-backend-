<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StorePredictionAction
{
    public function execute(User $user, array $data): Prediction
    {
        $league = League::findOrFail($data['league_id']);

        if (!$league->is_active) {
            throw ValidationException::withMessages([
                'league_id' => __('messages.league.closed'),
            ]);
        }

        if (!$league->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'league_id' => __('messages.league.not_member'),
            ]);
        }

        $match = FootballMatch::where('external_id', $data['match_id'])->firstOrFail();

        if ($match->competition_id !== $league->competition_id) {
            throw ValidationException::withMessages([
                'match_id' => __('messages.prediction.invalid_match'),
            ]);
        }

        if ($match->utc_date <= now()) {
            throw ValidationException::withMessages([
                'match_id' => __('messages.prediction.match_started'),
            ]);
        }

        return Prediction::updateOrCreate(
            [
                'user_id' => $user->id,
                'league_id' => $league->id,
                'match_id' => $data['match_id'],
            ],
            [
                'home_score' => $data['home_score'],
                'away_score' => $data['away_score'],
            ]
        );
    }
}
