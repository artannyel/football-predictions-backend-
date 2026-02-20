<?php

namespace App\Http\Controllers;

use App\Jobs\SendChatMessageNotification;
use App\Models\League;
use App\Services\FirestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function sendMessage(Request $request, string $leagueId, FirestoreService $firestore): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $league = League::findOrFail($leagueId);

        if (!$league->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('messages.league.not_member')], 403);
        }

        $disk = config('filesystems.default');
        $photoUrl = $user->photo_url ? asset(Storage::disk($disk)->url($user->photo_url)) : null;

        $messageData = [
            'text' => $request->input('text'),
            'userId' => $user->id,
            'userName' => $user->name,
            'userPhoto' => $photoUrl,
        ];

        $success = $firestore->addChatMessage($leagueId, $messageData);

        if (!$success) {
            return response()->json(['message' => __('messages.chat.failed')], 500);
        }

        SendChatMessageNotification::dispatch(
            $leagueId,
            $league->name,
            $user->id,
            $user->name,
            $request->input('text')
        );

        return response()->json(['message' => __('messages.chat.sent')]);
    }
}
