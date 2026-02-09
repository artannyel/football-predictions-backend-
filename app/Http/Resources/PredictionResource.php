<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionResource extends JsonResource
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
            'match_id' => $this->match_id,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'points_earned' => $this->points_earned,
            'created_at' => $this->created_at,
            'match' => new MatchResource($this->whenLoaded('match')),
        ];
    }
}
