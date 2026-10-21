<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BlockController;
use App\Models\HostelDetail;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('admin')->group(function () {
        Route::get('/hostels', fn() => HostelDetail::with('blocks.rooms.beds')->get());
        Route::get('/stats/rooms', [DashboardController::class, 'roomStats']);
        Route::get('/blocks', [BlockController::class, 'index']);
        Route::get('/blocks/{id}', [BlockController::class, 'show']);
    });
});
