<?php

namespace App\Actions;

use App\Models\League;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class CreateLeagueAction
{
    public function execute(User $user, array $data): League
    {
        $avatarPath = null;

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $filename = pathinfo($data['avatar']->hashName(), PATHINFO_FILENAME) . '.webp';
            $path = 'leagues/' . $filename;

            // Redimensiona para 500x500 (cover) e converte para WebP com 80% de qualidade
            $image = Image::read($data['avatar'])
                ->cover(500, 500)
                ->toWebp(80);

            // Usa o disco configurado no .env (public localmente, s3 em produção)
            Storage::disk(config('filesystems.default'))->put($path, (string) $image, 'public');

            $avatarPath = $path;
        }

        $league = League::create([
            'owner_id' => $user->id,
            'competition_id' => $data['competition_id'],
            'name' => $data['name'],
            'code' => League::generateCode(),
            'avatar' => $avatarPath,
            'description' => $data['description'] ?? null,
        ]);

        // Adiciona o dono como membro automaticamente
        $league->members()->attach($user->id);

        return $league;
    }
}
