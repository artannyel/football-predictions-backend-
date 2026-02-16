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
        if ($user->id !== $league->owner_id) {
            throw new AuthorizationException(__('messages.league.owner_only'));
        }

        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? $league->description,
        ];

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $disk = config('filesystems.default');

            if ($league->avatar && Storage::disk($disk)->exists($league->avatar)) {
                Storage::disk($disk)->delete($league->avatar);
            }

            $filename = pathinfo($data['avatar']->hashName(), PATHINFO_FILENAME) . '.webp';
            $path = 'leagues/' . $filename;

            $image = Image::read($data['avatar'])
                ->cover(500, 500)
                ->toWebp(80);

            Storage::disk($disk)->put($path, (string) $image, 'public');

            $updateData['avatar'] = $path;
        }

        $league->update($updateData);

        return $league;
    }
}
