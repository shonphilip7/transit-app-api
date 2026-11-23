<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KmlController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteStopsController;
use App\Http\Controllers\SwiftlyAdjustmentController;
use App\Http\Controllers\TrainViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/adjustments-data', [SwiftlyAdjustmentController::class, 'crudAdjustments']); // This is a third-party API not currently being used in the transit app.
Route::get('/trainview/{rr_route}/{stop_id}', [TrainViewController::class, 'index']);
Route::get('/routes', [RouteController::class, 'index']);
// Route::get('/{line}/stops', [TrainViewController::class, 'getStops']);
Route::get('/{route}/stops', [RouteStopsController::class, 'index'])
    ->where('route', '[A-Za-z0-9_-]+');
Route::get('/kml/{route_id}/{direction}', [KmlController::class, 'index']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
