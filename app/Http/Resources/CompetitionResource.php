<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->external_id, // Usamos o ID externo para o frontend
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'emblem' => $this->emblem,
            'area' => [
                'id' => $this->area->external_id,
                'name' => $this->area->name,
                'code' => $this->area->code,
                'flag' => $this->area->flag,
            ],
            'current_season' => [
                'id' => $this->currentSeason->external_id,
                'start_date' => $this->currentSeason->start_date->format('Y-m-d'),
                'end_date' => $this->currentSeason->end_date->format('Y-m-d'),
                'current_matchday' => $this->currentSeason->current_matchday,
            ],
        ];
    }
}
