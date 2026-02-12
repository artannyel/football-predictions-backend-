<?php

namespace App\Http\Controllers;

use App\Http\Resources\MatchResource;
use App\Models\FootballMatch;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $match = FootballMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('external_id', $id)
            ->firstOrFail();

        return response()->json([
            'data' => new MatchResource($match),
        ]);
    }
}
