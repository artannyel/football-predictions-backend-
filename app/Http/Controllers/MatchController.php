<?php

namespace App\Http\Controllers;

use App\Actions\GetMatchPredictionStatsAction;
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

    public function stats(int $id, GetMatchPredictionStatsAction $action): JsonResponse
    {
        // Verifica se o jogo existe
        if (!FootballMatch::where('external_id', $id)->exists()) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        $stats = $action->execute($id);

        return response()->json([
            'data' => $stats,
        ]);
    }
}
