<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrUpdateUserAction;
use App\DTOs\UserDTO;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(StoreUserRequest $request, CreateOrUpdateUserAction $action): JsonResponse
    {
        // O usuário já está autenticado pelo middleware, então pegamos o UID dele
        $firebaseUid = $request->user()->firebase_uid;

        $dto = UserDTO::fromRequest($request, $firebaseUid);

        $user = $action->execute($dto);

        return response()->json([
            'message' => __('messages.user.updated'),
            'data' => new UserResource($user),
        ]);
    }
}
