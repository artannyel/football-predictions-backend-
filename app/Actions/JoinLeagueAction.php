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
            throw new ModelNotFoundException("League with code {$code} not found.");
        }

        if (!$league->is_active) {
            throw ValidationException::withMessages([
                'code' => 'This league is closed and cannot accept new members.',
            ]);
        }

        if ($league->members()->where('user_id', $user->id)->exists()) {
            // Se já estiver na liga, apenas retorna a liga sem erro (idempotência)
            return $league;
        }

        $league->members()->attach($user->id);

        return $league;
    }
}
