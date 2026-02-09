<?php

use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.firebase'])->group(function () {

    Route::get('/user', function (Request $request) {
        return response()->json([
            'message' => 'Autenticado com sucesso via Firebase!',
            'user' => $request->user(),
        ]);
    });

    Route::post('/users', [UserController::class, 'store']);

    // Competitions
    Route::get('/competitions', [CompetitionController::class, 'index']);
    Route::get('/competitions/{id}/matches', [CompetitionController::class, 'matches']);
    Route::get('/competitions/{id}/matches/upcoming', [CompetitionController::class, 'upcomingMatches']);

    // Leagues
    Route::get('/leagues', [LeagueController::class, 'index']);
    Route::post('/leagues', [LeagueController::class, 'store']);
    Route::get('/leagues/{id}', [LeagueController::class, 'show']);
    Route::post('/leagues/{id}', [LeagueController::class, 'update']);
    Route::get('/leagues/{id}/ranking', [LeagueController::class, 'ranking']);
    Route::post('/leagues/join', [LeagueController::class, 'join']);

    // Predictions
    Route::get('/predictions', [PredictionController::class, 'index']);
    Route::post('/predictions', [PredictionController::class, 'store']);
    Route::get('/predictions/upcoming', [PredictionController::class, 'upcoming']);
    Route::get('/predictions/{id}', [PredictionController::class, 'show']);

    // Rules
    Route::get('/rules', [RulesController::class, 'index']);
});
