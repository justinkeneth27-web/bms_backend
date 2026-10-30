<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarangayConfigController;
use App\Http\Controllers\EvacuationCenterController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\ReliefDistributionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Barangay DRRM Operations API Routes
|--------------------------------------------------------------------------
*/

$healthHandler = function () {
    $dbConnected = false;
    $dbMessage = 'Disconnected';
    $counts = [
        'households' => 0,
        'evacuationCenters' => 0,
        'reliefDistributions' => 0,
        'activityLogs' => 0,
        'officialAccounts' => 0,
        'householdAccounts' => 0,
    ];

    try {
        DB::connection()->getPdo();
        $dbConnected = true;
        $dbMessage = 'Connected to PostgreSQL database "' . config('database.connections.pgsql.database') . '"';

        $rawCounts = DB::selectOne("
            SELECT 
                (SELECT count(*) FROM households) as households,
                (SELECT count(*) FROM evacuation_centers) as evacuation_centers,
                (SELECT count(*) FROM relief_distributions) as relief_distributions,
                (SELECT count(*) FROM activity_logs) as activity_logs,
                (SELECT count(*) FROM official_accounts) as official_accounts,
                (SELECT count(*) FROM household_accounts) as household_accounts
        ");

        if ($rawCounts) {
            $counts = [
                'households' => (int) ($rawCounts->households ?? 0),
                'evacuationCenters' => (int) ($rawCounts->evacuation_centers ?? 0),
                'reliefDistributions' => (int) ($rawCounts->relief_distributions ?? 0),
                'activityLogs' => (int) ($rawCounts->activity_logs ?? 0),
                'officialAccounts' => (int) ($rawCounts->official_accounts ?? 0),
                'householdAccounts' => (int) ($rawCounts->household_accounts ?? 0),
            ];
        }
    } catch (\Throwable $e) {
        $dbMessage = 'Database error: ' . $e->getMessage();
    }

    return response()->json([
        'status' => 'ok',
        'framework' => 'Laravel ' . app()->version(),
        'database' => [
            'connected' => $dbConnected,
            'message' => $dbMessage,
            'driver' => config('database.default'),
            'database' => config('database.connections.pgsql.database'),
        ],
        'counts' => $counts,
        'timestamp' => now('Asia/Manila')->toIso8601String(),
    ]);
};

// Health checks (Public system status)
Route::get('/health', $healthHandler);

// Authentication & Password Recovery (Strict 5 reqs/min limiter)
Route::prefix('auth')->middleware('throttle:auth-strict')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/resident/login', [AuthController::class, 'residentLogin']);
    Route::post('/resident/register', [AuthController::class, 'createHouseholdAccount']);
    Route::post('/security-question', [AuthController::class, 'getSecurityQuestion']);
    Route::post('/verify-security-answer', [AuthController::class, 'verifySecurityAnswer']);
    Route::post('/send-reset-otp', [AuthController::class, 'sendResetOtp']);
    Route::post('/verify-reset-otp', [AuthController::class, 'verifyResetOtp']);
    Route::post('/reset-password-with-otp', [AuthController::class, 'resetPasswordWithOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public / Read & Write endpoints (General 60 reqs/min DDoS protection)
Route::prefix('v1')->middleware('throttle:api')->group(function () use ($healthHandler) {
    Route::get('/health', $healthHandler);

    // Auth routes alias in v1 (Strict 5 reqs/min limiter)
    Route::prefix('auth')->middleware('throttle:auth-strict')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/resident/login', [AuthController::class, 'residentLogin']);
        Route::post('/resident/register', [AuthController::class, 'createHouseholdAccount']);
        Route::post('/security-question', [AuthController::class, 'getSecurityQuestion']);
        Route::post('/verify-security-answer', [AuthController::class, 'verifySecurityAnswer']);
        Route::post('/send-reset-otp', [AuthController::class, 'sendResetOtp']);
        Route::post('/verify-reset-otp', [AuthController::class, 'verifyResetOtp']);
        Route::post('/reset-password-with-otp', [AuthController::class, 'resetPasswordWithOtp']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Officials roster & account management
    Route::get('/officials', [AuthController::class, 'getAccounts']);
    Route::post('/officials', [AuthController::class, 'createAccount'])->middleware('throttle:api-write');
    Route::match(['put', 'patch', 'post'], '/officials/{username}', [AuthController::class, 'updateAccount'])->middleware('throttle:api-write');
    Route::delete('/officials/{username}', [AuthController::class, 'deleteAccount'])->middleware('throttle:api-write');

    // Household resident accounts
    Route::get('/household-accounts', [AuthController::class, 'getHouseholdAccounts']);
    Route::post('/household-accounts', [AuthController::class, 'createHouseholdAccount'])->middleware('throttle:auth-strict');

    // Households
    Route::get('/households', [HouseholdController::class, 'index']);
    Route::post('/households', [HouseholdController::class, 'store'])->middleware('throttle:api-write');
    Route::post('/households/broadcast-status', [HouseholdController::class, 'broadcastStatus'])->middleware('throttle:api-write');
    Route::match(['patch', 'post'], '/households/{id}/status', [HouseholdController::class, 'updateStatus'])->middleware('throttle:api-write');
    Route::match(['put', 'patch', 'post'], '/households/{id}', [HouseholdController::class, 'update'])->middleware('throttle:api-write');
    Route::delete('/households/{id}', [HouseholdController::class, 'destroy'])->middleware('throttle:api-write');

    // Evacuation Centers
    Route::get('/evacuation-centers', [EvacuationCenterController::class, 'index']);
    Route::post('/evacuation-centers', [EvacuationCenterController::class, 'store'])->middleware('throttle:api-write');
    Route::delete('/evacuation-centers/{id}', [EvacuationCenterController::class, 'destroy'])->middleware('throttle:api-write');
    Route::post('/evacuation-centers/{id}/assign', [EvacuationCenterController::class, 'assignHousehold'])->middleware('throttle:api-write');

    // Barangay Configurations
    Route::get('/barangay-configs', [BarangayConfigController::class, 'index']);
    Route::match(['put', 'patch', 'post'], '/barangay-configs/{name}', [BarangayConfigController::class, 'update'])->middleware('throttle:api-write');

    // Relief Distribution
    Route::get('/relief-distributions', [ReliefDistributionController::class, 'index']);
    Route::post('/relief-distributions', [ReliefDistributionController::class, 'store'])->middleware('throttle:api-write');

    // Activity & Audit Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::post('/activity-logs', [ActivityLogController::class, 'store'])->middleware('throttle:api-write');
    Route::get('/activity-logs/export-csv', [ActivityLogController::class, 'exportCsv']);

    // Protected Routes (Session Verification)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', function (Request $request) {
            return $request->user();
        });
    });
});
