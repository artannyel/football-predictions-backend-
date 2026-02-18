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
            'name' => $this->name,
            'email' => $this->email,
            'photo_url' => $this->photo_url ? asset(Storage::disk($disk)->url($this->photo_url)) : null,
            'notify_results' => (bool) $this->notify_results,
            'notify_reminders' => (bool) $this->notify_reminders,
            'created_at' => $this->created_at,
            'badges' => $this->whenLoaded('badges', function () use ($disk) {
                return $this->badges->groupBy('slug')->map(function ($group) use ($disk) {
                    $badge = $group->first();
                    return [
                        'slug' => $badge->slug,
                        'name' => $badge->name,
                        'description' => $badge->description,
                        'icon_url' => $badge->icon ? asset(Storage::disk($disk)->url($badge->icon)) : null,
                        'count' => $group->count(),
                    ];
                })->values();
            }),
        ];
    }
}
