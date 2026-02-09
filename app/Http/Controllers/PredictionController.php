<?php

namespace App\Http\Controllers;

use App\Actions\ListUpcomingPredictionsAction;
use App\Actions\StorePredictionAction;
use App\Http\Resources\PredictionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function store(Request $request, StorePredictionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'match_id' => 'required|exists:matches,external_id',
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
        ]);

        $prediction = $action->execute($request->user(), $validated);

        return response()->json([
            'message' => 'Prediction saved successfully',
            'data' => new PredictionResource($prediction),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $competitionId = $request->query('competition_id');

        $query = $request->user()->predictions()
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->orderBy('created_at', 'desc');

        if ($competitionId) {
            $query->whereHas('match', function ($q) use ($competitionId) {
                $q->where('competition_id', $competitionId);
            });
        }

        $predictions = $query->get();

        return response()->json([
            'data' => PredictionResource::collection($predictions),
        ]);
    }

    public function upcoming(Request $request, ListUpcomingPredictionsAction $action): JsonResponse
    {
        $competitionId = $request->query('competition_id');

        $predictions = $action->execute($request->user(), $competitionId);

        return response()->json([
            'data' => PredictionResource::collection($predictions),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        // Busca o palpite apenas se pertencer ao usuário autenticado
        $prediction = $request->user()->predictions()->with(['match.homeTeam', 'match.awayTeam'])->findOrFail($id);

        return response()->json([
            'data' => new PredictionResource($prediction),
        ]);
    }
}
