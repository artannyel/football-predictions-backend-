<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserProfileResource extends JsonResource
{
    public function __construct($resource, protected array $stats)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $disk = config('filesystems.default');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'photo_url' => $this->photo_url ? asset(Storage::disk($disk)->url($this->photo_url)) : null,
            'created_at' => $this->created_at,

            'badges' => $this->badges->groupBy('slug')->map(function ($group) use ($disk) {
                $badge = $group->first();
                return [
                    'slug' => $badge->slug,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon_url' => $badge->icon ? asset(Storage::disk($disk)->url($badge->icon)) : null,
                    'count' => $group->count(),
                ];
            })->values(),

            'career' => $this->stats['career'],
            'hall_of_fame' => $this->stats['hall_of_fame'],
        ];
    }
}
