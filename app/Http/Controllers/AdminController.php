<?php

namespace App\Http\Controllers;

use App\Actions\FixMatchAction;
use App\Actions\ListStuckMatchesAction;
use App\Actions\UpdateBadgesAction;
use App\Http\Resources\BadgeResource;
use App\Http\Resources\MatchResource;
use App\Jobs\DistributePowerUps;
use App\Jobs\RecalculateAllStats;
use App\Jobs\RecalculateBadges;
use App\Jobs\RecalculateGlobalStats;
use App\Jobs\RunImportMatches;
use App\Models\Badge;
use App\Models\Competition;
use App\Models\FootballMatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AdminController extends Controller
{
    public function recalculateStats(Request $request): JsonResponse
    {
        RecalculateAllStats::dispatch();

        return response()->json(['message' => 'Stats recalculation job dispatched.']);
    }

    public function recalculateBadges(Request $request): JsonResponse
    {
        $badgeSlug = $request->input('badge_slug');

        RecalculateBadges::dispatch($badgeSlug);

        return response()->json(['message' => 'Badges recalculation job dispatched.']);
    }

    public function recalculateGlobalStats(Request $request): JsonResponse
    {
        RecalculateGlobalStats::dispatch();

        return response()->json(['message' => 'Global Stats recalculation job dispatched.']);
    }

    public function listBadges(): JsonResponse
    {
        $badges = Badge::all();
        return response()->json(['data' => BadgeResource::collection($badges)]);
    }

    public function updateBadges(Request $request, UpdateBadgesAction $action): JsonResponse
    {
        $request->validate([
            'badges' => 'required|array',
            'badges.*.slug' => 'required|string|exists:badges,slug',
            'badges.*.name' => 'nullable|string',
            'badges.*.description' => 'nullable|string',
            'badges.*.icon_file' => 'nullable|image|max:10240',
        ]);

        $data = [];
        $badgesInput = $request->input('badges');
        $badgesFiles = $request->file('badges');

        foreach ($badgesInput as $index => $badgeData) {
            $item = $badgeData;
            if (isset($badgesFiles[$index]['icon_file'])) {
                $item['icon_file'] = $badgesFiles[$index]['icon_file'];
            }
            $data[] = $item;
        }

        $action->execute($data);

        return response()->json(['message' => 'Badges updated successfully.']);
    }

    public function listLogs(Request $request): JsonResponse
    {
        $path = storage_path('logs');
        $files = File::files($path);

        $logs = [];
        foreach ($files as $file) {
            $logs[] = [
                'name' => $file->getFilename(),
                'size' => round($file->getSize() / 1024, 2) . ' KB',
                'updated_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        return response()->json(['logs' => $logs]);
    }

    public function downloadLog(Request $request, string $filename)
    {
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return response()->json(['message' => 'Invalid filename'], 400);
        }

        $path = storage_path('logs/' . $filename);

        if (!File::exists($path)) {
            return response()->json(['message' => 'Log file not found'], 404);
        }

        return response()->download($path);
    }

    public function importMatches(Request $request): JsonResponse
    {
        $competitionId = $request->input('competition_id');

        RunImportMatches::dispatch($competitionId);

        return response()->json(['message' => 'Match import job dispatched.']);
    }

    public function distributePowerUps(Request $request): JsonResponse
    {
        DistributePowerUps::dispatch();

        return response()->json(['message' => 'Power-Up distribution job dispatched.']);
    }

    public function listStuckMatches(ListStuckMatchesAction $action): JsonResponse
    {
        $matches = $action->execute();

        return response()->json(['data' => MatchResource::collection($matches)]);
    }

    public function listMatches(Request $request): JsonResponse
    {
        $query = FootballMatch::with(['homeTeam', 'awayTeam', 'competition']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('competition_id')) {
            $query->where('competition_id', $request->input('competition_id'));
        }

        $timezone = 'America/Sao_Paulo';

        if ($request->has('date_from')) {
            $start = Carbon::createFromFormat('Y-m-d', $request->input('date_from'), $timezone)->startOfDay();
            $query->where('utc_date', '>=', $start->setTimezone('UTC'));
        }

        if ($request->has('date_to')) {
            $end = Carbon::createFromFormat('Y-m-d', $request->input('date_to'), $timezone)->endOfDay();
            $query->where('utc_date', '<=', $end->setTimezone('UTC'));
        }

        if ($request->has('team_name')) {
            $term = '%' . $request->input('team_name') . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('homeTeam', fn($sq) => $sq->where('name', 'like', $term)->orWhere('short_name', 'like', $term))
                  ->orWhereHas('awayTeam', fn($sq) => $sq->where('name', 'like', $term)->orWhere('short_name', 'like', $term));
            });
        }

        $matches = $query->orderBy('utc_date', 'desc')->paginate(20);

        return response()->json(MatchResource::collection($matches)->response()->getData(true));
    }

    public function getFilters(): JsonResponse
    {
        $competitions = Competition::select('external_id as id', 'name')->orderBy('name')->get();

        $statuses = [
            'SCHEDULED', 'TIMED', 'IN_PLAY', 'PAUSED', 'FINISHED', 'POSTPONED', 'SUSPENDED', 'CANCELED'
        ];

        return response()->json([
            'competitions' => $competitions,
            'statuses' => $statuses,
        ]);
    }

    public function fixMatch(Request $request, string $id, FixMatchAction $action): JsonResponse
    {
        $match = FootballMatch::where('external_id', $id)->firstOrFail();

        $validated = $request->validate([
            'status' => 'nullable|string',
            'score_fulltime_home' => 'nullable|integer',
            'score_fulltime_away' => 'nullable|integer',
            'score_halftime_home' => 'nullable|integer',
            'score_halftime_away' => 'nullable|integer',
            'score_extratime_home' => 'nullable|integer',
            'score_extratime_away' => 'nullable|integer',
            'score_penalties_home' => 'nullable|integer',
            'score_penalties_away' => 'nullable|integer',
            'score_winner' => 'nullable|string',
            'score_duration' => 'nullable|string',
            'unlock' => 'nullable|boolean',
        ]);

        $updatedMatch = $action->execute($match, $validated);

        return response()->json([
            'message' => 'Match updated manually.',
            'data' => new MatchResource($updatedMatch),
        ]);
    }
}
