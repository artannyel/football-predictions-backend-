<?php

namespace App\Actions;

use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $member = $league->members()->where('user_id', $user->id)->first();
        if (!$member) {
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

        return DB::transaction(function () use ($user, $league, $data, $member) {
            $existingPrediction = Prediction::where('user_id', $user->id)
                ->where('league_id', $league->id)
                ->where('match_id', $data['match_id'])
                ->first();

            $usePowerUp = $data['use_powerup'] ?? false;
            $currentPowerUp = $existingPrediction ? $existingPrediction->powerup_used : null;
            $newPowerUp = $currentPowerUp;

            // Se quer ativar e ainda não tem
            if ($usePowerUp && !$currentPowerUp) {
                // Calcula quantos já usou
                $usedCount = Prediction::where('user_id', $user->id)
                    ->where('league_id', $league->id)
                    ->whereNotNull('powerup_used')
                    ->count();

                $initial = $member->pivot->initial_powerups;

                if ($usedCount >= $initial) {
                    throw ValidationException::withMessages([
                        'use_powerup' => __('messages.powerup.insufficient_balance'),
                    ]);
                }

                $newPowerUp = 'x2';
            }
            // Se quer desativar e tinha
            elseif (!$usePowerUp && $currentPowerUp) {
                $newPowerUp = null;
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
                    'powerup_used' => $newPowerUp,
                ]
            );
        });
    }
}
