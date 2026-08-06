<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleImageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// `index` is the last issue's scope, hence explicit routes instead of
// `Route::apiResource(...)`. The `{vehicle}` parameter name is
// load-bearing: `UpdateVehicleRequest` assumes `route('vehicle')`.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
    Route::match(['put', 'patch'], '/vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

    Route::post('/vehicles/{vehicle}/images', [VehicleImageController::class, 'store']);
    Route::patch('/vehicles/{vehicle}/images/{imageId}/cover', [VehicleImageController::class, 'setCover']);
    Route::delete('/vehicles/{vehicle}/images/{imageId}', [VehicleImageController::class, 'destroy']);
});
