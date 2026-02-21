<?php

namespace App\Actions;

use App\Models\League;
use App\Models\User;
use App\Services\FirestoreService;
use App\Services\PowerUpService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class JoinLeagueAction
{
    public function __construct(
        protected PowerUpService $powerUpService,
        protected FirestoreService $firestore
    ) {}

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

        $initialPowerUps = $this->powerUpService->calculateInitialBalance($league->competition_id);

        $league->members()->attach($user->id, [
            'initial_powerups' => $initialPowerUps
        ]);

        // Envia mensagem de sistema no chat
        $this->firestore->addChatMessage($league->id, [
            'text' => "{$user->name} entrou na liga.",
            'type' => 'system',
            'userId' => null,
            'userName' => null,
            'userPhoto' => null,
        ]);

        return $league;
    }
}
