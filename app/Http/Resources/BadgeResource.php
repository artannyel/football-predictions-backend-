<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BadgeResource extends JsonResource
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
            'slug' => $this->slug,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'icon_url' => $this->icon ? asset(Storage::disk($disk)->url($this->icon)) : null,
        ];
    }
}
