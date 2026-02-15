<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwiftlyAdjustmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrainViewController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
/*
* Swiftly Adjustments 
*/
Route::get('/adjustments-data', [SwiftlyAdjustmentController::class, 'crudAdjustments']);
/**
 * TrainView Data
 */
Route::get('/trainview/{stop_id}', [TrainViewController::class, 'index']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');