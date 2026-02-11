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

        $user = User::where('firebase_uid', $dto->firebaseUid)->first();

        if ($dto->photo) {
            $disk = config('filesystems.default');

            if ($user && $user->photo_url) {
                Storage::disk($disk)->delete($user->photo_url);
            }

            $filename = $dto->photo->hashName();
            $path = 'users/' . $filename;

            $image = Image::read($dto->photo)
                ->cover(300, 300)
                ->toJpeg(80);

            Storage::disk($disk)->put($path, (string) $image, 'public');

            $userData['photo_url'] = $path;
        }

        return User::updateOrCreate(
            ['firebase_uid' => $dto->firebaseUid],
            $userData
        );
    }
}
