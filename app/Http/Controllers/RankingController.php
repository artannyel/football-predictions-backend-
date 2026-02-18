<?php

namespace App\Http\Controllers;

use App\Actions\GetGlobalRankingAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RankingController extends Controller
{
    public function global(Request $request, GetGlobalRankingAction $action): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value === 'GLOBAL') return;
                if (preg_match('/^\d{4}$/', $value)) return; // YYYY
                if (preg_match('/^\d{4}-\d{2}$/', $value)) return; // YYYY-MM

                $fail('The '.$attribute.' must be GLOBAL, YYYY or YYYY-MM.');
            }],
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $period = $request->query('period', 'GLOBAL');
        $limit = $request->query('limit', 10);

        $data = $action->execute($request->user(), $period, $limit);

        return response()->json($data);
    }
}
