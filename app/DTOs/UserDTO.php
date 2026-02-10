<?php

namespace App\DTOs;

use Illuminate\Http\UploadedFile;

readonly class UserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $firebaseUid,
        public ?UploadedFile $photo = null,
    ) {}

    public static function fromRequest($request, string $firebaseUid): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            firebaseUid: $firebaseUid,
            photo: $request->file('photo_url'),
        );
    }
}
