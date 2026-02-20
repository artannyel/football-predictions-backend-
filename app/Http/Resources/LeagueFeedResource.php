<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LeagueFeedResource extends JsonResource
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
            'created_at' => $this->created_at,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user_name,
                'photo_url' => $this->user_photo ? asset(Storage::disk($disk)->url($this->user_photo)) : null,
            ],
            'badge' => [
                'name' => $this->badge_name,
                'slug' => $this->badge_slug,
                'icon_url' => $this->badge_icon ? asset(Storage::disk($disk)->url($this->badge_icon)) : null,
            ],
            'match' => $this->match_id ? [
                'id' => $this->match_id,
                'home_team' => [
                    'name' => $this->home_team,
                    'crest' => $this->home_team_crest,
                ],
                'away_team' => [
                    'name' => $this->away_team,
                    'crest' => $this->away_team_crest,
                ],
                'score' => [
                    'home' => $this->score_fulltime_home,
                    'away' => $this->score_fulltime_away,
                ],
            ] : null,
        ];
    }
}
