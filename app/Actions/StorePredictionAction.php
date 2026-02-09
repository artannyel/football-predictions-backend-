<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class StorePredictionAction
{
    public function execute(User $user, array $data): Prediction
    {
        $match = FootballMatch::where('external_id', $data['match_id'])->firstOrFail();

        // Verifica se o jogo já começou (com uma margem de segurança de 5 minutos, opcional)
        if ($match->utc_date <= now()) {
            throw ValidationException::withMessages([
                'match_id' => 'The match has already started. Predictions are closed.',
            ]);
        }

        return Prediction::updateOrCreate(
            [
                'user_id' => $user->id,
                'match_id' => $data['match_id'],
            ],
            [
                'home_score' => $data['home_score'],
                'away_score' => $data['away_score'],
            ]
        );
    }
}
