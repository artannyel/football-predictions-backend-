<?php

namespace App\Actions;

use App\Models\League;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateLeagueAction
{
    public function execute(User $user, League $league, array $data): League
    {
        // Verifica se o usuário é o dono
        if ($user->id !== $league->owner_id) {
            throw new AuthorizationException('Only the owner can edit this league.');
        }

        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? $league->description,
        ];

        // Processamento do Avatar
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            // 1. Deleta o avatar antigo se existir
            if ($league->avatar && Storage::disk('public')->exists($league->avatar)) {
                Storage::disk('public')->delete($league->avatar);
            }

            // 2. Processa e salva o novo
            $filename = $data['avatar']->hashName();
            $path = 'leagues/' . $filename;

            $image = Image::read($data['avatar'])
                ->cover(500, 500)
                ->toJpeg(80);

            Storage::disk('public')->put($path, (string) $image);

            $updateData['avatar'] = $path;
        }

        $league->update($updateData);

        return $league;
    }
}
