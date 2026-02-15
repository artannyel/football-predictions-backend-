<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LeagueResource extends JsonResource
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
            'code' => $this->code,
            'avatar' => $this->avatar ? asset(Storage::disk($disk)->url($this->avatar)) : null,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'pending_predictions_count' => $this->when(isset($this->pending_predictions_count), $this->pending_predictions_count),
            'competition' => [
                'id' => $this->competition->external_id,
                'name' => $this->competition->name,
                'emblem' => $this->competition->emblem,
            ],
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'photo_url' => $this->owner->photo_url ? asset(Storage::disk($disk)->url($this->owner->photo_url)) : null,
            ],
            'members_count' => $this->members_count ?? $this->members()->count(),
            'my_points' => $this->whenPivotLoaded('league_user', function () {
                return $this->pivot->points;
            }),
        ];
    }
}
