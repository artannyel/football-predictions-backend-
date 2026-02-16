<?php

namespace App\Http\Controllers;

use App\Actions\UpdateBadgesAction;
use App\Jobs\RecalculateAllStats;
use App\Jobs\RecalculateBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function updateBadges(Request $request, UpdateBadgesAction $action): JsonResponse
    {
        $request->validate([
            'badges' => 'required|array',
            'badges.*.slug' => 'required|string|exists:badges,slug',
            'badges.*.name' => 'nullable|string',
            'badges.*.description' => 'nullable|string',
            'badges.*.icon_file' => 'nullable|image|max:10240',
        ]);

        // Processa upload de arquivos
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
}
