<?php

namespace App\Http\Controllers;

use App\Actions\CreateLeagueAction;
use App\Actions\JoinLeagueAction;
use App\Actions\ListUpcomingCompetitionMatchesAction;
use App\Actions\ListUserLeaguesAction;
use App\Actions\UpdateLeagueAction;
use App\Http\Resources\LeagueFeedResource;
use App\Http\Resources\LeagueResource;
use App\Http\Resources\MatchResource;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeagueController extends Controller
{
    public function index(Request $request, ListUserLeaguesAction $action): JsonResponse
    {
        $filters = $request->only(['competition_id', 'name', 'status', 'per_page']);

        $leagues = $action->execute($request->user(), $filters);

        return response()->json(
            LeagueResource::collection($leagues)->response()->getData(true)
        );
    }

    public function store(Request $request, CreateLeagueAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'competition_id' => 'required|exists:competitions,external_id',
            'avatar' => 'nullable|image|max:10240',
            'description' => 'nullable|string',
        ]);

        $league = $action->execute($request->user(), $validated);

        return response()->json([
            'message' => __('messages.league.created'),
            'data' => new LeagueResource($league->load(['competition', 'owner'])),
        ], 201);
    }

    public function update(Request $request, string $id, UpdateLeagueAction $action): JsonResponse
    {
        $league = League::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|image|max:10240',
            'description' => 'nullable|string',
        ]);

        try {
            $updatedLeague = $action->execute($request->user(), $league, $validated);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('messages.league.updated'),
            'data' => new LeagueResource($updatedLeague->load(['competition', 'owner'])),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $league = $request->user()->leagues()
            ->with(['competition', 'owner'])
            ->where('leagues.id', $id)
            ->first();

        if (!$league) {
            $exists = League::find($id);
            if ($exists) {
                return response()->json(['message' => __('messages.league.not_member')], 403);
            }
            return response()->json(['message' => __('messages.league.not_found')], 404);
        }

        return response()->json([
            'data' => new LeagueResource($league),
        ]);
    }

    public function ranking(Request $request, string $id): JsonResponse
    {
        $league = League::findOrFail($id);

        if (!$league->members()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => __('messages.league.not_member')], 403);
        }

        $disk = config('filesystems.default');

        $members = $league->members()
            ->orderByPivot('points', 'desc')
            ->orderByPivot('exact_score_count', 'desc')
            ->orderByPivot('winner_diff_count', 'desc')
            ->orderByPivot('winner_goal_count', 'desc')
            ->orderByPivot('winner_only_count', 'desc')
            ->orderByPivot('error_count', 'asc')
            ->paginate(50);

        return response()->json([
            'league_name' => $league->name,
            'data' => $members->map(function ($member, $index) use ($members, $disk) {
                return [
                    'rank' => ($members->currentPage() - 1) * $members->perPage() + $index + 1,
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'photo_url' => $member->photo_url ? asset(Storage::disk($disk)->url($member->photo_url)) : null,
                    'points' => $member->pivot->points,
                    'stats' => [
                        'exact_score' => $member->pivot->exact_score_count,
                        'winner_diff' => $member->pivot->winner_diff_count,
                        'winner_goal' => $member->pivot->winner_goal_count,
                        'winner_only' => $member->pivot->winner_only_count,
                        'errors' => $member->pivot->error_count,
                        'total' => $member->pivot->total_predictions,
                    ]
                ];
            }),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'total' => $members->total(),
            ]
        ]);
    }

    public function upcomingMatches(Request $request, string $id, ListUpcomingCompetitionMatchesAction $action): JsonResponse
    {
        $league = League::findOrFail($id);

        if (!$league->members()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => __('messages.league.not_member')], 403);
        }

        $matches = $action->execute($id, $request->user()->id);

        return response()->json([
            'data' => MatchResource::collection($matches),
        ]);
    }

    public function join(Request $request, JoinLeagueAction $action): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $league = $action->execute($request->user(), $validated['code']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'message' => __('messages.league.joined'),
            'data' => new LeagueResource($league->load(['competition', 'owner'])),
        ]);
    }

    public function feed(Request $request, string $id): JsonResponse
    {
        $league = League::findOrFail($id);

        if (!$league->members()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => __('messages.league.not_member')], 403);
        }

        $feed = DB::table('user_badges')
            ->join('users', 'user_badges.user_id', '=', 'users.id')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->leftJoin('matches', 'user_badges.match_id', '=', 'matches.external_id')
            ->leftJoin('teams as home', 'matches.home_team_id', '=', 'home.external_id')
            ->leftJoin('teams as away', 'matches.away_team_id', '=', 'away.external_id')
            ->where('user_badges.league_id', $id)
            ->select(
                'user_badges.id',
                'user_badges.created_at',
                'users.id as user_id',
                'users.name as user_name',
                'users.photo_url as user_photo',
                'badges.name as badge_name',
                'badges.icon as badge_icon',
                'badges.slug as badge_slug',
                'matches.external_id as match_id',
                'home.short_name as home_team',
                'home.crest as home_team_crest',
                'away.short_name as away_team',
                'away.crest as away_team_crest',
                'matches.score_fulltime_home',
                'matches.score_fulltime_away'
            )
            ->orderBy('user_badges.created_at', 'desc')
            ->paginate(20);

        return response()->json(
            LeagueFeedResource::collection($feed)->response()->getData(true)
        );
    }
}
