<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrUpdateUserAction;
use App\Actions\GetUserProfileStatsAction;
use App\DTOs\UserDTO;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(StoreUserRequest $request, CreateOrUpdateUserAction $action): JsonResponse
    {
        $firebaseUid = $request->user()->firebase_uid;
        $dto = UserDTO::fromRequest($request, $firebaseUid);
        $user = $action->execute($dto);

        return response()->json([
            'message' => __('messages.user.updated'),
            'data' => new UserResource($user),
        ]);
    }

    public function profile(Request $request, GetUserProfileStatsAction $action): JsonResponse
    {
        $user = $request->user()->load('badges'); // Carrega badges aqui
        $stats = $action->execute($user);

        return response()->json([
            'data' => new UserProfileResource($user, $stats),
        ]);
    }
}
