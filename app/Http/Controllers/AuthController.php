<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\HouseholdAccount;
use App\Models\OfficialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Authenticate official and issue API token.
     */
    public function login(Request $request): JsonResponse
    {
        $identifier = $request->input('username') ?? $request->input('email');
        if ($identifier) {
            $request->merge(['username' => $identifier]);
        }

        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'barangay' => 'nullable|string',
        ]);

        $cleanUsername = str_replace('.', '', strtolower(trim($validated['username'])));
        $query = OfficialAccount::where(function ($q) use ($validated, $cleanUsername) {
            $q->where('username', $validated['username'])
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        });
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
                'email' => $account->email ?? '',
            ],
        ]);
    }

    /**
     * Authenticate resident / household head via registered email and password.
     */
    public function residentLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $cleanEmail = strtolower(trim($validated['email']));
        $account = HouseholdAccount::whereRaw('LOWER(email) = ?', [$cleanEmail])->first();

        if (!$account || !$account->verifyPassword($validated['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resident credentials. Please check your registered email and password.',
            ], 401);
        }

        $token = $account->createToken('barangay-resident-token')->plainTextToken;

        // Fetch associated household record
        $household = null;
        if (!empty($account->household_id)) {
            $household = Household::find($account->household_id);
        }
        if (!$household) {
            $household = Household::where('head_of_family', $account->head_of_family)
                ->where('barangay', $account->barangay)
                ->first();
        }

        ActivityLog::record(
            'LOGIN',
            $account->head_of_family,
            'Resident / Household Head',
            $account->barangay,
            "Household Portal Access (" . ($account->household_id ?? 'Direct') . ")",
            "Resident {$account->head_of_family} authenticated into Citizen Emergency Status Portal."
        );

        return response()->json([
            'success' => true,
            'token' => $token,
            'account' => [
                'id' => (string) $account->id,
                'householdId' => $account->household_id,
                'email' => $account->email,
                'headOfFamily' => $account->head_of_family,
                'barangay' => $account->barangay,
                'contactNumber' => $account->contact_number,
                'createdAt' => (string) $account->created_at,
            ],
            'household' => $household,
        ]);
    }

    /**
     * Register a new household resident account in backend PostgreSQL.
     */
    public function createHouseholdAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:household_accounts,email',
            'password' => 'required|string|min:6',
            'householdId' => 'nullable|string',
            'headOfFamily' => 'required|string',
            'barangay' => 'required|string',
            'contactNumber' => 'nullable|string',
        ]);

        $account = HouseholdAccount::create([
            'id' => 'acc-' . Str::uuid()->toString(),
            'household_id' => $validated['householdId'] ?? null,
            'email' => strtolower(trim($validated['email'])),
            'password_hash' => Hash::make($validated['password']),
            'head_of_family' => $validated['headOfFamily'],
            'barangay' => $validated['barangay'],
            'contact_number' => $validated['contactNumber'] ?? null,
        ]);

        ActivityLog::record(
            'CREATE',
            $validated['headOfFamily'],
            'Resident Registrant',
            $validated['barangay'],
            "Household Account ({$account->email})",
            "New resident emergency portal account registered for {$account->head_of_family} in Barangay {$account->barangay}."
        );

        return response()->json([
            'success' => true,
            'message' => 'Resident account registered successfully.',
            'data' => [
                'id' => (string) $account->id,
                'householdId' => $account->household_id,
                'email' => $account->email,
                'headOfFamily' => $account->head_of_family,
                'barangay' => $account->barangay,
                'contactNumber' => $account->contact_number,
                'createdAt' => (string) $account->created_at,
            ],
        ], 201);
    }

    /**
     * List registered household accounts (without password hashes).
     */
    public function getHouseholdAccounts(Request $request): JsonResponse
    {
        $query = HouseholdAccount::query();

        // Server-Side Jurisdiction Query Scoping
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay)) {
            $query->where('barangay', $user->barangay);
        } elseif ($request->filled('barangay')) {
            $query->where('barangay', $request->query('barangay'));
        }

        $accounts = $query->orderBy('created_at', 'desc')->get();
        $transformed = $accounts->map(function ($a) {
            return [
                'id' => (string) $a->id,
                'householdId' => $a->household_id,
                'email' => $a->email,
                'headOfFamily' => $a->head_of_family,
                'barangay' => $a->barangay,
                'contactNumber' => $a->contact_number,
                'createdAt' => (string) $a->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $transformed]);
    }

    /**
     * Lookup official account details for Gmail OTP password reset.
     */
    public function getSecurityQuestion(Request $request): JsonResponse
    {
        $identifier = $request->input('username') ?? $request->input('email');
        if ($identifier) {
            $request->merge(['username' => $identifier]);
        }

        $validated = $request->validate([
            'username' => 'required|string',
        ]);

        $cleanUsername = str_replace('.', '', strtolower(trim($validated['username'])));
        $account = OfficialAccount::where(function ($q) use ($validated, $cleanUsername) {
            $q->where('username', $validated['username'])
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        })->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found in registered official roster.',
            ], 404);
        }

        $defaultEmail = $account->barangay === 'Buang' ? 'buangg2026@gmail.com' : ($account->barangay === 'Nagsipit' ? 'nagsipit2026@gmail.com' : null);
        $email = !empty($account->email) ? $account->email : $defaultEmail;
        $hasEmail = !empty($email);
        $maskedEmail = null;
        if ($hasEmail) {
            $parts = explode('@', $email);
            $name = $parts[0];
            $domain = $parts[1] ?? 'gmail.com';
            $masked = strlen($name) > 2
                ? substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 2)) . substr($name, -1)
                : $name . '**';
            $maskedEmail = "{$masked}@{$domain}";
        }

        $defaultOfficialName = $account->barangay === 'Buang' ? 'Hon. Rolando M. Santos' : 'Hon. Cynthia P. Ramos';

        return response()->json([
            'success' => true,
            'barangay' => $account->barangay,
            'officialName' => $account->official_name ?: $defaultOfficialName,
            'position' => $account->position ?: 'Punong Barangay / DRRM Chief',
            'username' => $account->username,
            'hasEmail' => $hasEmail,
            'email' => $email,
            'emailMasked' => $maskedEmail,
        ]);
    }

    /**
     * Verify security answer (Deprecated - replaced by Gmail OTP).
     */
    public function verifySecurityAnswer(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Security questions have been retired. Please use official Gmail OTP recovery.',
        ], 400);
    }

    /**
     * Send 6-digit OTP code to official's Gmail for password recovery.
     */
    public function sendResetOtp(Request $request): JsonResponse
    {
        $identifier = $request->input('username') ?? $request->input('email');
        if ($identifier) {
            $request->merge(['username' => $identifier]);
        }

        $validated = $request->validate([
            'username' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $cleanUsername = str_replace('.', '', strtolower(trim($validated['username'])));
        $account = OfficialAccount::where(function ($q) use ($validated, $cleanUsername) {
            $q->where('username', $validated['username'])
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        })->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Official account not found in registered roster.',
            ], 404);
        }

        // Require the user to type their Gmail address and validate against registered recovery email
        $defaultBarangayEmail = $account->barangay === 'Buang' ? 'buangg2026@gmail.com' : ($account->barangay === 'Nagsipit' ? 'nagsipit2026@gmail.com' : null);
        $expectedEmail = !empty($account->email) ? strtolower(trim($account->email)) : $defaultBarangayEmail;

        if (empty($validated['email'])) {
            return response()->json([
                'success' => false,
                'need_email' => true,
                'message' => 'Please enter your registered Gmail address to receive the verification OTP code.',
            ], 422);
        }

        $recipientEmail = strtolower(trim($validated['email']));
        $isLinkRequest = $request->boolean('link_email') || $request->input('action') === 'link';

        if ($isLinkRequest || empty($account->email)) {
            // Update and link official recovery Gmail directly in PostgreSQL cloud database
            $account->email = $recipientEmail;
            $account->save();
        } else if ($expectedEmail && strcasecmp($recipientEmail, $expectedEmail) !== 0) {
            return response()->json([
                'success' => false,
                'message' => "The Gmail address entered does not match the official recovery email registered for Barangay {$account->barangay}.",
            ], 422);
        }

        // Generate 6-digit cryptographic OTP
        $otp = (string) random_int(100000, 999999);

        // Store OTP in cache for 10 minutes
        $cacheKey = "official_reset_otp:" . strtolower(trim($account->username));
        Cache::put($cacheKey, Hash::make($otp), now()->addMinutes(10));

        // Mask recipient email (e.g. b***g@gmail.com)
        $parts = explode('@', $recipientEmail);
        $namePart = $parts[0];
        $domainPart = $parts[1] ?? 'gmail.com';
        $maskedName = strlen($namePart) > 2
            ? substr($namePart, 0, 1) . str_repeat('*', max(1, strlen($namePart) - 2)) . substr($namePart, -1)
            : $namePart . '**';
        $maskedEmail = "{$maskedName}@{$domainPart}";

        $isNagsipit = (strcasecmp($account->barangay, 'Nagsipit') === 0);
        $mailerName = $isNagsipit ? 'smtp_nagsipit' : 'smtp_buang';
        $senderEmail = $isNagsipit
            ? config('mail.mailers.smtp_nagsipit.username', 'nagsipit2026@gmail.com')
            : config('mail.mailers.smtp_buang.username', 'buangg2026@gmail.com');
        $senderPassword = $isNagsipit
            ? config('mail.mailers.smtp_nagsipit.password', 'muogkhchjjwpdmsq')
            : config('mail.mailers.smtp_buang.password', 'unfvjoxzvqtatclt');

        $smtpConfigured = !empty($senderPassword);
        $mailSent = false;
        $mailError = null;

        if ($smtpConfigured) {
            try {
                Mail::mailer($mailerName)->html("
                    <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif; max-width:540px; margin:0 auto; border:1px solid #e2e8f0; border-radius:12px; padding:28px; background:#ffffff; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);'>
                        <div style='margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:16px;'>
                            <h2 style='color:#1e3a8a; margin:0; font-size:18px; text-transform:uppercase; letter-spacing:0.5px;'>Barangay Disaster Operations Console</h2>
                        </div>
                        <h3 style='color:#0f172a; margin-top:0;'>Official Password Recovery OTP</h3>
                        <p style='color:#475569; font-size:13px; line-height:1.6;'>
                            Hello <strong>{$account->official_name}</strong> ({$account->position} — Barangay {$account->barangay}),
                        </p>
                        <p style='color:#475569; font-size:13px; line-height:1.6;'>
                            We received a request to reset the password for your official command console account: <code style='background:#f1f5f9; color:#0f172a; padding:2px 6px; border-radius:4px;'>{$account->username}</code>.
                        </p>
                        <div style='background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:20px; text-align:center; margin:24px 0;'>
                            <div style='font-size:11px; font-weight:bold; color:#1d4ed8; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;'>Your 6-Digit Verification Code</div>
                            <span style='font-size:32px; font-weight:800; letter-spacing:8px; color:#1e40af; font-family:monospace;'>{$otp}</span>
                        </div>
                        <p style='color:#64748b; font-size:11px; line-height:1.5;'>
                            ⏱️ This code will expire in <strong>10 minutes</strong>. If you did not request this recovery code, please notify your Barangay DRRM lead immediately.
                        </p>
                        <hr style='border:none; border-top:1px solid #f1f5f9; margin:20px 0;' />
                        <div style='font-size:10px; color:#94a3b8; text-align:center;'>
                            Official automated security message from Barangay {$account->barangay} DRRM System. Do not reply to this email.
                        </div>
                    </div>
                ", function ($message) use ($recipientEmail, $account, $otp, $senderEmail) {
                    $message->from($senderEmail, "Barangay {$account->barangay} DRRM Operations Console")
                        ->to($recipientEmail)
                        ->subject("🔐 [Barangay {$account->barangay} DRRM] Official Password Reset OTP: {$otp}");
                });
                $mailSent = true;
            } catch (\Throwable $e) {
                Log::warning("Gmail SMTP delivery error ({$mailerName}): " . $e->getMessage());
                $mailError = $e->getMessage();
            }
        }

        ActivityLog::record(
            'RESET_OTP_REQUESTED',
            $account->official_name,
            $account->position,
            $account->barangay,
            "Official Account (@{$account->username})",
            "Password reset OTP generated for {$account->official_name}."
        );

        if (!$smtpConfigured) {
            return response()->json([
                'success' => false,
                'message' => 'Gmail SMTP is not configured in backend/.env. Please configure MAIL_USERNAME and MAIL_PASSWORD in backend/.env.',
                'email' => $recipientEmail,
                'email_masked' => $maskedEmail,
                'has_email' => true,
            ], 500);
        }

        if (!$mailSent) {
            return response()->json([
                'success' => false,
                'message' => "Failed to deliver OTP to {$recipientEmail} via Gmail SMTP. Error: " . ($mailError ?? 'Unknown connection error'),
                'email' => $recipientEmail,
                'email_masked' => $maskedEmail,
                'has_email' => true,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "A 6-digit OTP verification code has been sent to {$recipientEmail}. Please check your Gmail inbox and enter the code below.",
            'email' => $recipientEmail,
            'email_masked' => $maskedEmail,
            'has_email' => true,
        ]);
    }

    /**
     * Verify 6-digit OTP code before resetting password.
     */
    public function verifyResetOtp(Request $request): JsonResponse
    {
        $identifier = $request->input('username') ?? $request->input('email');
        if ($identifier) {
            $request->merge(['username' => $identifier]);
        }

        $validated = $request->validate([
            'username' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $cleanUsername = str_replace('.', '', strtolower(trim($validated['username'])));
        $account = OfficialAccount::where(function ($q) use ($validated, $cleanUsername) {
            $q->where('username', $validated['username'])
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        })->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Official account not found.'], 404);
        }

        $cacheKey = "official_reset_otp:" . strtolower(trim($account->username));
        $cachedHash = Cache::get($cacheKey);

        if (!$cachedHash || !Hash::check($validated['otp'], $cachedHash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP verification code. Please check your email or request a new code.',
            ], 422);
        }

        // Mark OTP as verified for 15 minutes
        Cache::put("official_reset_verified:" . strtolower(trim($account->username)), true, now()->addMinutes(15));

        return response()->json([
            'success' => true,
            'message' => 'Email OTP identity verified successfully.',
        ]);
    }

    /**
     * Complete password reset using verified OTP.
     */
    public function resetPasswordWithOtp(Request $request): JsonResponse
    {
        $identifier = $request->input('username') ?? $request->input('email');
        if ($identifier) {
            $request->merge(['username' => $identifier]);
        }

        $validated = $request->validate([
            'username' => 'required|string',
            'otp' => 'required|string|size:6',
            'newPassword' => 'required|string|min:6',
        ]);

        $cleanUsername = str_replace('.', '', strtolower(trim($validated['username'])));
        $account = OfficialAccount::where(function ($q) use ($validated, $cleanUsername) {
            $q->where('username', $validated['username'])
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        })->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Official account not found.'], 404);
        }

        $cacheKey = "official_reset_otp:" . strtolower(trim($account->username));
        $cachedHash = Cache::get($cacheKey);
        $isVerified = Cache::get("official_reset_verified:" . strtolower(trim($account->username)));

        if ((!$cachedHash || !Hash::check($validated['otp'], $cachedHash)) && !$isVerified) {
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed or expired. Please request a new code.',
            ], 422);
        }

        $account->password_hash = Hash::make($validated['newPassword']);
        $account->save();

        // Invalidate OTP cache
        Cache::forget($cacheKey);
        Cache::forget("official_reset_verified:" . strtolower(trim($account->username)));

        ActivityLog::record(
            'PASSWORD_RESET',
            $account->official_name,
            $account->position,
            $account->barangay,
            "Official Account (@{$account->username})",
            "Password reset successfully completed via Gmail Email OTP for {$account->official_name}."
        );

        return response()->json([
            'success' => true,
            'message' => 'Official password updated successfully. You can now log in with your new password.',
        ]);
    }

    /**
     * Reset official password (Deprecated - replaced by Gmail OTP resetPasswordWithOtp).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Password reset via security question has been retired. Please use official Gmail OTP verification.',
        ], 400);
    }

    /**
     * Terminate official/resident session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $actorName = $user->official_name ?? $user->head_of_family ?? 'User';
            $position = $user->position ?? 'Resident';
            ActivityLog::record(
                'LOGOUT',
                $actorName,
                $position,
                $user->barangay,
                'System Access',
                "Session terminated and token revoked for {$actorName}."
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

        // Server-Side Jurisdiction Query Scoping
        $user = auth('sanctum')->user() ?: $request->user();
        if ($user && !empty($user->barangay)) {
            $query->where('barangay', $user->barangay);
        } elseif ($request->filled('barangay')) {
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
                'email' => $a->email ?? '',
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
            'email' => 'nullable|email',
        ]);

        $account = OfficialAccount::create([
            'username' => $validated['username'],
            'password_hash' => Hash::make($validated['password']),
            'barangay' => $validated['barangay'],
            'official_name' => $validated['officialName'],
            'position' => $validated['position'],
            'phone' => $validated['phone'] ?? '0917-000-0000',
            'email' => $validated['email'] ?? null,
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
                'email' => $account->email ?? '',
            ],
        ], 201);
    }

    /**
     * Update official account profile or credentials.
     */
    public function updateAccount(Request $request, string $username): JsonResponse
    {
        $cleanUsername = str_replace('.', '', strtolower(trim($username)));
        $account = OfficialAccount::where(function ($q) use ($username, $cleanUsername) {
            $q->where('username', $username)
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        })->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Official account not found.'], 404);
        }

        $validated = $request->validate([
            'officialName' => 'nullable|string',
            'position' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'password' => 'nullable|string|min:6',
        ]);

        if (!empty($validated['officialName'])) {
            $account->official_name = $validated['officialName'];
        }
        if (!empty($validated['position'])) {
            $account->position = $validated['position'];
        }
        if (!empty($validated['phone'])) {
            $account->phone = $validated['phone'];
        }
        if (array_key_exists('email', $validated)) {
            $account->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $account->password_hash = Hash::make($validated['password']);
        }

        $account->save();

        ActivityLog::record(
            'ACCOUNT_UPDATE',
            $request->input('officerName', $account->official_name),
            $request->input('officerRole', $account->position),
            $account->barangay,
            "Official Account (@{$account->username})",
            "Updated official account details/credentials for {$account->official_name}."
        );

        return response()->json([
            'success' => true,
            'message' => 'Official account updated successfully.',
            'data' => [
                'id' => (string) $account->id,
                'username' => $account->username,
                'barangay' => $account->barangay,
                'officialName' => $account->official_name,
                'position' => $account->position,
                'phone' => $account->phone,
                'email' => $account->email ?? '',
            ],
        ]);
    }

    /**
     * Delete registered official account.
     */
    public function deleteAccount(Request $request, string $username): JsonResponse
    {
        $cleanUsername = str_replace('.', '', strtolower(trim($username)));
        $account = OfficialAccount::where(function ($q) use ($username, $cleanUsername) {
            $q->where('username', $username)
              ->orWhereRaw("LOWER(REPLACE(username, '.', '')) = ?", [$cleanUsername]);
        })->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Official account not found.'], 404);
        }

        $officialName = $account->official_name;
        $brgy = $account->barangay;
        $account->delete();

        ActivityLog::record(
            'ACCOUNT_DELETE',
            $request->input('officerName', 'Barangay DRRM Chief'),
            $request->input('officerRole', 'DRRM Chief'),
            $brgy,
            "Official Account (@{$username})",
            "Deleted official account for {$officialName}."
        );

        return response()->json(['success' => true, 'message' => 'Official account deleted successfully.']);
    }
}
