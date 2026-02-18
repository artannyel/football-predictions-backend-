<?php

namespace App\Http\Controllers;

use App\Actions\UpdateBadgesAction;
use App\Jobs\DistributePowerUps;
use App\Jobs\RecalculateAllStats;
use App\Jobs\RecalculateBadges;
use App\Jobs\RecalculateGlobalStats;
use App\Jobs\RunImportMatches;
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

    public function updateBadges(Request $request, UpdateBadgesAction $action): JsonResponse
    {
        $request->validate([
            'badges' => 'required|array',
            'badges.*.slug' => 'required|string|exists:badges,slug',
            'badges.*.name' => 'nullable|string',
            'badges.*.description' => 'nullable|string',
            'badges.*.icon_file' => 'nullable|image|max:2048',
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
}
