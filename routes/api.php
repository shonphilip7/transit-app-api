<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwiftlyAdjustmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrainViewController;
use App\Http\Controllers\KmlController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
/*
* Swiftly Adjustments 
*/
Route::get('/adjustments-data', [SwiftlyAdjustmentController::class, 'crudAdjustments']);
/**
 * TrainView API ex: http://localhost/api/trainview/VYTA
 */
Route::get('/trainview/{stop_id}', [TrainViewController::class, 'index']);
Route::get('/routes', [TrainViewController::class, 'getRoutes']);
/**
 * KML API ex: http://localhost/api/kml/R1/1
 */
Route::get('/kml/{route_id}/{direction}', [KmlController::class, 'index']);
/**
 * APIs for authentication
 */
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');