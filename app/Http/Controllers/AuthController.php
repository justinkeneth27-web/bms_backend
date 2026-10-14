<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\OfficialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate official and issue API token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'barangay' => 'nullable|string',
        ]);

        $query = OfficialAccount::where('username', $validated['username']);
        if (!empty($validated['barangay'])) {
            $query->where('barangay', $validated['barangay']);
        }

        $account = $query->first();

        if (!$account || !$account->verifyPassword($validated['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid official credentials or unauthorized barangay jurisdiction.',
            ], 401);
        }

        $token = $account->createToken('barangay-drrm-token')->plainTextToken;

        ActivityLog::record(
            'LOGIN',
            $account->official_name,
            $account->position,
            $account->barangay,
            'System Access Authentication',
            "Official session authenticated successfully for {$account->official_name} ({$account->position})."
        );

        return response()->json([
            'success' => true,
            'token' => $token,
            'official' => [
                'username' => $account->username,
                'barangay' => $account->barangay,
                'officialName' => $account->official_name,
                'position' => $account->position,
                'phone' => $account->phone,
            ],
        ]);
    }

    /**
     * Get security question for password reset.
     */
    public function getSecurityQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
        ]);

        $account = OfficialAccount::where('username', $validated['username'])->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found in registered official roster.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'question' => $account->security_question,
            'barangay' => $account->barangay,
        ]);
    }

    /**
     * Reset password verifying security answer.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'securityAnswer' => 'required|string',
            'newPassword' => 'required|string|min:6',
        ]);

        $account = OfficialAccount::where('username', $validated['username'])->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        if (strcasecmp(trim($account->security_answer), trim($validated['securityAnswer'])) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Security verification answer does not match official records.',
            ], 422);
        }

        $account->password_hash = Hash::make($validated['newPassword']);
        $account->save();

        ActivityLog::record(
            'PASSWORD_RESET',
            $account->official_name,
            $account->position,
            $account->barangay,
            'Security Protocol',
            "Master password successfully reset via official security question verification."
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please log in with your new credentials.',
        ]);
    }

    /**
     * Terminate official session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            ActivityLog::record(
                'LOGOUT',
                $user->official_name,
                $user->position,
                $user->barangay,
                'System Access',
                "Official session terminated and token revoked for {$user->official_name}."
            );
            $user->currentAccessToken()->delete();
        }

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    /**
     * List registered official accounts.
     */
    public function getAccounts(Request $request): JsonResponse
    {
        $query = OfficialAccount::query();
        if ($request->filled('barangay')) {
            $query->where('barangay', $request->query('barangay'));
        }
        $accounts = $query->orderBy('barangay')->orderBy('official_name')->get();
        $transformed = $accounts->map(function ($a) {
            return [
                'id' => (string) $a->id,
                'username' => $a->username,
                'barangay' => $a->barangay,
                'officialName' => $a->official_name,
                'position' => $a->position,
                'phone' => $a->phone,
                'securityQuestion' => $a->security_question,
            ];
        });

        return response()->json(['success' => true, 'data' => $transformed]);
    }

    /**
     * Create a new official account.
     */
    public function createAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:official_accounts,username',
            'password' => 'required|string|min:6',
            'barangay' => 'required|string',
            'officialName' => 'required|string',
            'position' => 'required|string',
            'phone' => 'nullable|string',
            'securityQuestion' => 'required|string',
            'securityAnswer' => 'required|string',
        ]);

        $account = OfficialAccount::create([
            'username' => $validated['username'],
            'password_hash' => Hash::make($validated['password']),
            'barangay' => $validated['barangay'],
            'official_name' => $validated['officialName'],
            'position' => $validated['position'],
            'phone' => $validated['phone'] ?? '0917-000-0000',
            'security_question' => $validated['securityQuestion'],
            'security_answer' => $validated['securityAnswer'],
        ]);

        ActivityLog::record(
            'ACCOUNT_CREATE',
            $validated['officialName'],
            $validated['position'],
            $validated['barangay'],
            'Official Account Provisioning',
            "New official account created: {$account->username} ({$account->position}) in Barangay {$account->barangay}."
        );

        return response()->json([
            'success' => true,
            'message' => 'Official account registered successfully.',
            'data' => [
                'id' => (string) $account->id,
                'username' => $account->username,
                'barangay' => $account->barangay,
                'officialName' => $account->official_name,
                'position' => $account->position,
                'phone' => $account->phone,
                'securityQuestion' => $account->security_question,
            ],
        ], 201);
    }
}
