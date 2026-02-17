<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->external_id,
            'utc_date' => $this->utc_date->toIso8601String(),
            'status' => $this->status,
            'matchday' => $this->matchday,
            'stage' => $this->stage,
            'group' => $this->group,
            'home_team' => [
                'id' => $this->homeTeam?->external_id,
                'name' => $this->homeTeam?->name,
                'short_name' => $this->homeTeam?->short_name,
                'crest' => $this->homeTeam?->crest,
            ],
            'away_team' => [
                'id' => $this->awayTeam?->external_id,
                'name' => $this->awayTeam?->name,
                'short_name' => $this->awayTeam?->short_name,
                'crest' => $this->awayTeam?->crest,
            ],
            'score' => [
                'winner' => $this->score_winner,
                'duration' => $this->score_duration,
                'full_time' => [
                    'home' => $this->score_fulltime_home,
                    'away' => $this->score_fulltime_away,
                ],
                'half_time' => [
                    'home' => $this->score_halftime_home,
                    'away' => $this->score_halftime_away,
                ],
                'extra_time' => [
                    'home' => $this->score_extratime_home,
                    'away' => $this->score_extratime_away
                    ],
                'penalties' => [
                    'home' => $this->score_penalties_home,
                    'away' => $this->score_penalties_away
                ],
            ],
        ];
    }
}
