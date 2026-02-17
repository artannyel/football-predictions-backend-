<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LeagueMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = config('filesystems.default');
        $leagueId = $this->pivot->league_id;

        $badges = $this->badges()
            ->wherePivot('league_id', $leagueId)
            ->get()
            ->groupBy('slug')
            ->map(function ($group) use ($disk) {
                $badge = $group->first();
                return [
                    'slug' => $badge->slug,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon_url' => $badge->icon ? asset(Storage::disk($disk)->url($badge->icon)) : null,
                    'count' => $group->count(),
                ];
            })->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo_url' => $this->photo_url ? asset(Storage::disk($disk)->url($this->photo_url)) : null,
            'stats' => [
                'points' => $this->pivot->points,
                'exact_score' => $this->pivot->exact_score_count,
                'winner_diff' => $this->pivot->winner_diff_count,
                'winner_goal' => $this->pivot->winner_goal_count,
                'winner_only' => $this->pivot->winner_only_count,
                'errors' => $this->pivot->error_count,
                'total' => $this->pivot->total_predictions,
            ],
            'badges' => $badges,
        ];
    }
}
