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

        // 1. Cria ou atualiza o usuário primeiro para ter o ID
        $user = User::updateOrCreate(
            ['firebase_uid' => $dto->firebaseUid],
            $userData
        );

        // 2. Processa a foto se houver
        if ($dto->photo) {
            $disk = config('filesystems.default');

            // Nome fixo baseado no ID do usuário
            $filename = $user->id . '.webp';
            $path = 'users/' . $filename;

            $image = Image::read($dto->photo)
                ->cover(500, 500)
                ->toWebp(80);

            // Sobrescreve o arquivo existente (se houver)
            Storage::disk($disk)->put($path, (string) $image, 'public');

            // Atualiza o caminho no banco se mudou (ou se era null)
            if ($user->photo_url !== $path) {
                $user->update(['photo_url' => $path]);
            }
        }

        return $user;
    }
}
