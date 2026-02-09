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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'avatar' => $this->avatar ? asset(Storage::url($this->avatar)) : null,
            'description' => $this->description,
            'competition' => [
                'id' => $this->competition->external_id,
                'name' => $this->competition->name,
                'emblem' => $this->competition->emblem,
            ],
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'photo_url' => $this->owner->photo_url,
            ],
            'members_count' => $this->members_count ?? $this->members()->count(),
            'my_points' => $this->whenPivotLoaded('league_user', function () {
                return $this->pivot->points;
            }),
            'ranking' => $this->when($request->routeIs('leagues.show'), function () {
                return $this->members->map(function ($member) {
                    return [
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'photo_url' => $member->photo_url,
                        'points' => $member->pivot->points,
                    ];
                })->sortByDesc('points')->values();
            }),
        ];
    }
}
