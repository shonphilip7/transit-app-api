<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KmlController;
use App\Http\Controllers\SwiftlyAdjustmentController;
use App\Http\Controllers\TrainViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/adjustments-data', [SwiftlyAdjustmentController::class, 'crudAdjustments']);
Route::get('/trainview/{rr_route}/{stop_id}', [TrainViewController::class, 'index']);
Route::get('/routes', [TrainViewController::class, 'getRoutes']);
Route::get('/{line}/stops', [TrainViewController::class, 'getStops']);
Route::get('/kml/{route_id}/{direction}', [KmlController::class, 'index']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
