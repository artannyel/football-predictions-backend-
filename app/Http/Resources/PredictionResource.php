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
            'result_type' => $this->result_type,
            'powerup_used' => $this->powerup_used, // Novo campo
            'created_at' => $this->created_at,
            'match' => new MatchResource($this->whenLoaded('match')),
            'my_prediction' => $this->when(isset($this->my_prediction), function () {
                return $this->my_prediction ? [
                    'id' => $this->my_prediction->id,
                    'home_score' => $this->my_prediction->home_score,
                    'away_score' => $this->my_prediction->away_score,
                    'points_earned' => $this->my_prediction->points_earned,
                    'result_type' => $this->my_prediction->result_type,
                    'powerup_used' => $this->my_prediction->powerup_used,
                ] : null;
            }),
        ];
    }
}
