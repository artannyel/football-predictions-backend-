<?php

namespace App\Actions;

use App\Models\League;
use App\Models\User;
use App\Services\PowerUpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class CreateLeagueAction
{
    public function __construct(protected PowerUpService $powerUpService) {}

    public function execute(User $user, array $data): League
    {
        $avatarPath = null;

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $filename = pathinfo($data['avatar']->hashName(), PATHINFO_FILENAME) . '.webp';
            $path = 'leagues/' . $filename;

            $image = Image::read($data['avatar'])
                ->cover(500, 500)
                ->toWebp(80);

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

        $initialPowerUps = $this->powerUpService->calculateInitialBalance($data['competition_id']);

        $league->members()->attach($user->id, [
            'initial_powerups' => $initialPowerUps
        ]);

        return $league;
    }
}
