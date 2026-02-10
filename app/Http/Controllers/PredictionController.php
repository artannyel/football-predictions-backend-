<?php

namespace App\Http\Controllers;

use App\Actions\ListOtherUserPredictionsAction;
use App\Actions\ListUpcomingPredictionsAction;
use App\Actions\StorePredictionAction;
use App\Http\Resources\PredictionResource;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    // Esta rota será movida para dentro de /leagues/{id}/predictions
    public function store(Request $request, StorePredictionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'league_id' => 'required|uuid|exists:leagues,id',
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
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $leagueId = $request->query('league_id');
        $perPage = $request->query('per_page', 20);

        $query = $request->user()->predictions()
            ->with(['match.homeTeam', 'match.awayTeam'])
            ->orderBy('created_at', 'desc');

        if ($leagueId) {
            $query->where('league_id', $leagueId);
        }

        $predictions = $query->paginate($perPage);

        return response()->json(
            PredictionResource::collection($predictions)->response()->getData(true)
        );
    }

    // Esta rota será movida para /leagues/{id}/predictions/upcoming
    public function upcoming(Request $request, ListUpcomingPredictionsAction $action): JsonResponse
    {
        $request->validate([
            'league_id' => 'required|uuid|exists:leagues,id',
        ]);

        $leagueId = $request->query('league_id');

        $predictions = $action->execute($request->user(), $leagueId);

        return response()->json([
            'data' => PredictionResource::collection($predictions),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $prediction = $request->user()->predictions()->with(['match.homeTeam', 'match.awayTeam'])->findOrFail($id);

        return response()->json([
            'data' => new PredictionResource($prediction),
        ]);
    }

    public function userPredictions(Request $request, string $userId, ListOtherUserPredictionsAction $action): JsonResponse
    {
        $request->validate([
            'league_id' => 'required|uuid|exists:leagues,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $leagueId = $request->query('league_id');
        $perPage = $request->query('per_page', 20);
        $currentUser = $request->user();

        // 1. Verifica se a liga existe
        $league = League::findOrFail($leagueId);

        // 2. Verifica se o usuário solicitante é membro da liga
        if (!$league->members()->where('user_id', $currentUser->id)->exists()) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        // 3. Verifica se o usuário alvo é membro da liga
        if (!$league->members()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'Target user is not a member of this league.'], 404);
        }

        $predictions = $action->execute($userId, $leagueId, $perPage);

        return response()->json(
            PredictionResource::collection($predictions)->response()->getData(true)
        );
    }
}
