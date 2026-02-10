<?php

namespace App\Actions;

use App\DTOs\UserDTO;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class CreateOrUpdateUserAction
{
    public function execute(UserDTO $dto): User
    {
        $userData = [
            'name' => $dto->name,
            'email' => $dto->email,
        ];

        // Busca o usuário para saber se precisa deletar foto antiga
        $user = User::where('firebase_uid', $dto->firebaseUid)->first();

        if ($dto->photo) {
            // Deleta foto antiga se existir
            if ($user && $user->photo_url) {
                Storage::disk('public')->delete($user->photo_url);
            }

            $filename = $dto->photo->hashName();
            $path = 'users/' . $filename;

            // Redimensiona para 300x300 (avatar) e converte para JPG
            $image = Image::read($dto->photo)
                ->cover(300, 300)
                ->toJpeg(80);

            Storage::disk('public')->put($path, (string) $image);

            $userData['photo_url'] = $path;
        }

        return User::updateOrCreate(
            ['firebase_uid' => $dto->firebaseUid],
            $userData
        );
    }
}
