<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EvacuationCenterController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\ReliefDistributionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Barangay DRRM Operations API Routes
|--------------------------------------------------------------------------
*/

// Authentication & Password Recovery
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/security-question', [AuthController::class, 'getSecurityQuestion']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public / Read & Write endpoints (or wrap with auth:sanctum middleware for production)
Route::prefix('v1')->group(function () {
    // Auth routes alias in v1
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/security-question', [AuthController::class, 'getSecurityQuestion']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Officials roster & account creation
    Route::get('/officials', [AuthController::class, 'getAccounts']);
    Route::post('/officials', [AuthController::class, 'createAccount']);
    // Households
    Route::get('/households', [HouseholdController::class, 'index']);
    Route::post('/households', [HouseholdController::class, 'store']);
    Route::patch('/households/{id}/status', [HouseholdController::class, 'updateStatus']);
    Route::delete('/households/{id}', [HouseholdController::class, 'destroy']);

    // Evacuation Centers
    Route::get('/evacuation-centers', [EvacuationCenterController::class, 'index']);
    Route::post('/evacuation-centers/{id}/assign', [EvacuationCenterController::class, 'assignHousehold']);

    // Relief Distribution
    Route::get('/relief-distributions', [ReliefDistributionController::class, 'index']);
    Route::post('/relief-distributions', [ReliefDistributionController::class, 'store']);

    // Activity & Audit Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::post('/activity-logs', [ActivityLogController::class, 'store']);
    Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv']);

    // Protected Routes (Session Verification)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', function (Request $request) {
            return $request->user();
        });
    });
});
