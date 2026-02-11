<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = config('filesystems.default');

        return [
            'id' => $this->id,
            'firebase_uid' => $this->firebase_uid,
            'name' => $this->name,
            'email' => $this->email,
            'photo_url' => $this->photo_url ? asset(Storage::disk($disk)->url($this->photo_url)) : null,
            'created_at' => $this->created_at,
        ];
    }
}
