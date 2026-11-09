<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StudentController;
use App\Models\HostelDetail;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('admin')->group(function () {
        Route::get('/hostels', fn() => HostelDetail::with('blocks.rooms.beds')->get());
        Route::get('/stats/rooms', [DashboardController::class, 'roomStats']);
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::get('/blocks', [BlockController::class, 'index']);
        Route::get('/blocks/numbers', [BlockController::class, 'numbers']);
        Route::get('/blocks/{number}', [BlockController::class, 'show'])->whereNumber('number');
        Route::get('/blocks/{number}/floors', [BlockController::class, 'floors'])->whereNumber('number');
        Route::get('/rooms/{roomId}', [RoomController::class, 'show'])->where('roomId', 'R[0-9]+');
        Route::get('/students/search', [StudentController::class, 'search'])->middleware('throttle:120,1');
    });
});
