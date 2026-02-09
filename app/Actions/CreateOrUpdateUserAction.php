<?php

namespace App\Actions;

use App\DTOs\UserDTO;
use App\Models\User;

class CreateOrUpdateUserAction
{
    public function execute(UserDTO $dto): User
    {
        return User::updateOrCreate(
            ['firebase_uid' => $dto->firebaseUid],
            [
                'name' => $dto->name,
                'email' => $dto->email,
            ]
        );
    }
}
