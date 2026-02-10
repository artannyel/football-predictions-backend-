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

        // Verifica se a liga está ativa
        if (!$league->is_active) {
            throw ValidationException::withMessages([
                'league_id' => 'This league is closed.',
            ]);
        }

        // Verifica se o usuário pertence à liga
        if (!$league->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'league_id' => 'You are not a member of this league.',
            ]);
        }

        $match = FootballMatch::where('external_id', $data['match_id'])->firstOrFail();

        // Verifica se o jogo pertence à competição da liga
        if ($match->competition_id !== $league->competition_id) {
            throw ValidationException::withMessages([
                'match_id' => 'This match does not belong to the league competition.',
            ]);
        }

        // Verifica se o jogo já começou
        if ($match->utc_date <= now()) {
            throw ValidationException::withMessages([
                'match_id' => 'The match has already started. Predictions are closed.',
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
