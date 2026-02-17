<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rota pública de Health Check / Ping
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'football-predictions-api'
    ]);
});

// Admin Routes (Protected by X-Admin-Key)
Route::middleware(['auth.admin'])->prefix('admin')->group(function () {
    Route::post('/recalculate-stats', [AdminController::class, 'recalculateStats']);
    Route::post('/recalculate-badges', [AdminController::class, 'recalculateBadges']);
    Route::post('/badges', [AdminController::class, 'updateBadges']);
    Route::post('/import-matches', [AdminController::class, 'importMatches']);
    Route::post('/distribute-powerups', [AdminController::class, 'distributePowerUps']);

    // Logs
    Route::get('/logs', [AdminController::class, 'listLogs']);
    Route::get('/logs/{filename}', [AdminController::class, 'downloadLog']);
});

Route::middleware(['auth.firebase'])->group(function () {

    // Rota leve para boot/auth
    Route::get('/user', function (Request $request) {
        return response()->json([
            'message' => __('messages.auth.success'),
            'user' => new UserResource($request->user()),
        ]);
    });

    // Rota completa para tela de perfil (Meu)
    Route::get('/user/profile', [UserController::class, 'profile']);

    // Rota completa para tela de perfil (Outro Usuário)
    Route::get('/users/{id}/profile', [UserController::class, 'showProfile']);

    Route::post('/users', [UserController::class, 'store']);

    // Competitions
    Route::get('/competitions', [CompetitionController::class, 'index']);
    Route::get('/competitions/{id}/matches', [CompetitionController::class, 'matches']);

    // Matches
    Route::get('/matches/{id}', [MatchController::class, 'show']);
    Route::get('/matches/{id}/stats', [MatchController::class, 'stats']);

    // Leagues
    Route::get('/leagues', [LeagueController::class, 'index']);
    Route::post('/leagues', [LeagueController::class, 'store']);
    Route::post('/leagues/join', [LeagueController::class, 'join']);
    Route::get('/leagues/{id}', [LeagueController::class, 'show']);
    Route::post('/leagues/{id}', [LeagueController::class, 'update']);
    Route::get('/leagues/{id}/ranking', [LeagueController::class, 'ranking']);

    // Jogos disponíveis para palpitar NA LIGA
    Route::get('/leagues/{id}/matches/upcoming', [LeagueController::class, 'upcomingMatches']);

    // Predictions
    Route::get('/predictions', [PredictionController::class, 'index']); // Pode filtrar por ?league_id=...
    Route::post('/predictions', [PredictionController::class, 'store']); // Exige league_id no body
    Route::get('/predictions/upcoming', [PredictionController::class, 'upcoming']); // Exige ?league_id=...
    Route::get('/predictions/user/{userId}', [PredictionController::class, 'userPredictions']);
    Route::get('/predictions/{id}', [PredictionController::class, 'show']);

    // Rules
    Route::get('/rules', [RulesController::class, 'index']);
});
