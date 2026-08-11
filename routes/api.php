<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\YieldController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/projects', [ProjectController::class, 'index']);

    Route::get('/projects/{project}/yields/summary', [YieldController::class, 'summary']);
    Route::apiResource('projects.yields', YieldController::class)->shallow();

    Route::get('/projects/{project}/sales', [SaleController::class, 'index']);
    Route::post('/projects/{project}/sales', [SaleController::class, 'store']);

    Route::get('/projects/{project}/expenses', [ExpenseController::class, 'index']);
    Route::post('/projects/{project}/expenses', [ExpenseController::class, 'store']);
});