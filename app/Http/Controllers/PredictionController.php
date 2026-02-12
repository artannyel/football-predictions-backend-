<?php

namespace App\Http\Controllers;

use App\Actions\ListOtherUserPredictionsAction;
use App\Actions\ListUpcomingPredictionsAction;
use App\Actions\StorePredictionAction;
use App\Http\Resources\PredictionResource;
use App\Models\League;
use App\Models\Prediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PredictionController extends Controller
{
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
            'message' => __('messages.prediction.saved'),
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
        $prediction = Prediction::with(['match.homeTeam', 'match.awayTeam'])->find($id);

        if (!$prediction) {
            return response()->json(['message' => __('messages.prediction.not_found')], 404);
        }

        if ($prediction->user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.prediction.unauthorized')], 403);
        }

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
        $disk = config('filesystems.default');

        $league = League::findOrFail($leagueId);

        if (!$league->members()->where('user_id', $currentUser->id)->exists()) {
            return response()->json(['message' => __('messages.league.not_member')], 403);
        }

        $targetMember = $league->members()->where('user_id', $userId)->first();

        if (!$targetMember) {
            return response()->json(['message' => __('messages.league.target_not_member')], 404);
        }

        $predictions = $action->execute($userId, $leagueId, $perPage);
        $paginatedData = PredictionResource::collection($predictions)->response()->getData(true);

        return response()->json([
            'user' => [
                'id' => $targetMember->id,
                'name' => $targetMember->name,
                'photo_url' => $targetMember->photo_url ? asset(Storage::disk($disk)->url($targetMember->photo_url)) : null,
                'stats' => [
                    'points' => $targetMember->pivot->points,
                    'exact_score' => $targetMember->pivot->exact_score_count,
                    'winner_diff' => $targetMember->pivot->winner_diff_count,
                    'winner_goal' => $targetMember->pivot->winner_goal_count,
                    'winner_only' => $targetMember->pivot->winner_only_count,
                    'errors' => $targetMember->pivot->error_count,
                    'total' => $targetMember->pivot->total_predictions,
                ]
            ],
            'predictions' => $paginatedData
        ]);
    }
}
