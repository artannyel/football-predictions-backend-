<?php

namespace App\Http\Controllers;

use App\Actions\ListActiveCompetitionsAction;
use App\Actions\ListCompetitionMatchesAction;
use App\Actions\ListUpcomingCompetitionMatchesAction;
use App\Http\Resources\CompetitionResource;
use App\Http\Resources\MatchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    public function index(ListActiveCompetitionsAction $action): JsonResponse
    {
        $competitions = $action->execute();

        return response()->json([
            'data' => CompetitionResource::collection($competitions),
        ]);
    }

    public function matches(int $id, ListCompetitionMatchesAction $action): JsonResponse
    {
        $matches = $action->execute($id);

        return response()->json([
            'data' => MatchResource::collection($matches),
        ]);
    }

    public function upcomingMatches(Request $request, int $id, ListUpcomingCompetitionMatchesAction $action): JsonResponse
    {
        $matches = $action->execute($id, $request->user()->id);

        return response()->json([
            'data' => MatchResource::collection($matches),
        ]);
    }
}
