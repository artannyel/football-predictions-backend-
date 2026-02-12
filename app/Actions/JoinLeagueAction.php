<?php

namespace App\Actions;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class JoinLeagueAction
{
    public function execute(User $user, string $code): League
    {
        $league = League::where('code', strtoupper($code))->first();

        if (!$league) {
            throw new ModelNotFoundException(__('messages.league.not_found', ['code' => $code]));
        }

        if (!$league->is_active) {
            throw ValidationException::withMessages([
                'code' => __('messages.league.closed'),
            ]);
        }

        if ($league->members()->where('user_id', $user->id)->exists()) {
            return $league;
        }

        $league->members()->attach($user->id);

        return $league;
    }
}
