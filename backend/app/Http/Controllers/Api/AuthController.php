<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationToken;
use App\Models\LoginHistory;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\SuspiciousLoginDetected;
use App\Notifications\VerifyEmailLink;
use App\Support\MfaService;
use App\Support\MfaVerification;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use AuthorizesApi;

    /** Lifetime of a pending MFA challenge (seconds). */
    public const MFA_CHALLENGE_TTL = 300;

    /** Maximum number of wrong MFA codes per challenge. */
    public const MFA_MAX_ATTEMPTS = 5;

    public function __construct(private readonly MfaService $mfa)
    {
    }

    /**
     * Login with email and password, issue Sanctum token.
     * Per-account lockout: after N failed attempts (system setting) the
     * account is locked for M minutes — independent of the shared IP throttle.
     *
     * When the account has a second factor enrolled, no token is issued here:
     * the response carries a short-lived challenge that must be completed via
     * POST /api/auth/mfa/verify with the authenticator (or recovery) code.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $this->recordLoginAttempt($request, null, (string) $request->email, false, false, 'unknown_account');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Locked account check
        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $minutes = (int) ceil($user->locked_until->diffInMinutes(now()));
            $this->recordLoginAttempt($request, $user, $user->email, false, false, 'account_locked');

            throw ValidationException::withMessages([
                'email' => ["Account temporarily locked. Try again in {$minutes} minute(s)."],
            ]);
        }

        if ($user->status === 'suspended') {
            $this->recordLoginAttempt($request, $user, $user->email, false, false, 'account_suspended');

            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Contact support.'],
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            $maxAttempts = (int) SystemSetting::get('security.login_max_attempts', 5);
            $lockoutMinutes = (int) SystemSetting::get('security.login_lockout_minutes', 15);

            $failed = (int) $user->failed_attempts + 1;
            $updates = ['failed_attempts' => $failed];
            if ($failed >= $maxAttempts) {
                $updates['locked_until'] = now()->addMinutes($lockoutMinutes);
                $updates['failed_attempts'] = 0;
                Log::warning('Account locked after failed logins', [
                    'user_id' => $user->id, 'ip' => $request->ip(), 'attempts' => $failed,
                ]);
            }
            User::where('id', $user->id)->update($updates);

            $this->recordLoginAttempt($request, $user, $user->email, false, false, 'invalid_password');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Success: reset counters
        User::where('id', $user->id)->update([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);

        // Second factor gate — no token is issued before it is satisfied.
        if ($user->hasMfaEnabled()) {
            $challenge = $this->createMfaChallenge($user);
            $this->recordLoginAttempt($request, $user, $user->email, false, false, 'mfa_pending');

            return response()->json([
                'success' => true,
                'mfa_required' => true,
                'challenge' => $challenge,
                'expires_in' => self::MFA_CHALLENGE_TTL,
                'message' => 'Two-factor verification is required to finish signing in.',
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->recordLoginAttempt($request, $user, $user->email, true, false, null);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->userPayload($user, $request->header('X-Agency-Id')),
        ]);
    }

    /** Legacy input adapter: every self-registration requires agency approval. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'fullName' => 'required|string|min:3|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'cnic' => 'required|string|max:20',
            'agencyOrBranch' => 'nullable|string|min:3|max:120',
            'sectorFocus' => 'nullable|string|max:100',
        ]);

        // CNIC is no longer retained by self-registration. Replace rather than
        // merge so unvalidated role/status/tenant fields cannot reach creation.
        $request->replace([
            'name' => $data['agencyOrBranch'] ?? mb_substr($data['fullName'] . ' & Associates', 0, 120),
            'contactName' => $data['fullName'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'],
        ]);

        return app(AgencyController::class)->register($request);
    }

    /**
     * Get authenticated user info (incl. agency memberships + effective perms).
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $this->userPayload($request->user(), $request->header('X-Agency-Id')),
            'agency_id' => $this->agencyId($request),
        ]);
    }

    /**
     * Logout - revoke current token.
     */
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            MfaVerification::forgetToken((int) $request->user()->id, (int) $token->id);
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /* ==================== Password reset (G-4) ==================== */

    /**
     * POST /api/auth/forgot-password
     * Enumeration-safe: the response is always generic; the reset mail is
     * sent only when the account exists. Rate-limited 3/min (route).
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(['email' => $request->email]);

        Log::info('Password reset requested', ['status' => $status, 'ip' => $request->ip()]);

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
    }

    /**
     * POST /api/auth/reset-password
     * Consumes the broker token (hashed at rest), rotates the password and
     * revokes every active session — a reset is a global logout event.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Reset is a global logout: revoke every issued token and
                // clear every MFA verification stamp.
                MfaVerification::forgetAll((int) $user->id);
                $user->tokens()->delete();

                $user->forceFill([
                    'failed_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                $this->writeAudit($request, 'auth.password_reset', (string) $user->id, 'Password reset completed; all sessions revoked.', [
                    'entity_type' => 'user',
                    'entity_id' => (string) $user->id,
                    'metadata' => ['revoked_tokens' => true],
                ]);

                // Security notification: the account owner is told that the
                // password changed and that every other session was signed out.
                $user->notify(new PasswordChangedNotification($request->ip(), true));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password updated. Please sign in with your new password.',
            ]);
        }

        throw ValidationException::withMessages([
            'token' => [__($status)],
        ]);
    }

    /* ==================== Email verification (G-4) ==================== */

    /**
     * GET|POST /api/auth/email/verify?email=&token=
     * Consumes the hashed verification token, marks the account verified and
     * removes the token. Verification is tracked but NOT required for login —
     * agency approval remains the workspace gate (documented decision).
     */
    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|min:20|max:120',
        ]);

        $row = EmailVerificationToken::where('email', strtolower($data['email']))->first();

        if (!$row || !hash_equals($row->token_hash, hash('sha256', $data['token']))) {
            return response()->json([
                'success' => false,
                'message' => 'This verification link is invalid.',
            ], 400);
        }

        if ($row->created_at !== null && $row->created_at->lt(now()->subMinutes(60))) {
            $row->delete();

            return response()->json([
                'success' => false,
                'message' => 'This verification link has expired. Request a new one after signing in.',
            ], 400);
        }

        $user = User::where('email', strtolower($data['email']))->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $alreadyVerified = $user->email_verified_at !== null;

        DB::transaction(function () use ($user, $row, $alreadyVerified): void {
            if (!$alreadyVerified) {
                $user->markEmailVerified();
            }
            $row->delete();
        });

        if (!$alreadyVerified) {
            $this->writeAudit($request, 'auth.email_verified', (string) $user->id, 'Email address verified.', [
                'entity_type' => 'user',
                'entity_id' => (string) $user->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'already_verified' => $alreadyVerified,
            'message' => $alreadyVerified
                ? 'Email was already verified.'
                : 'Email verified. Thank you!',
        ]);
    }

    /**
     * POST /api/auth/email/resend (authenticated, throttle 3/min).
     * Issues a fresh verification token and mails the link.
     */
    public function resendEmailVerification(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'success' => true,
                'already_verified' => true,
                'message' => 'Your email is already verified.',
            ]);
        }

        $user->notify(VerifyEmailLink::issueFor($user->email));

        return response()->json([
            'success' => true,
            'already_verified' => false,
            'message' => 'A new verification link has been sent to your email.',
        ]);
    }

    /**
     * Logout everywhere - revoke all tokens of this user.
     */
    public function logoutAll(Request $request)
    {
        MfaVerification::forgetAll((int) $request->user()->id);
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices.',
        ]);
    }

    /* ==================== Multi-factor authentication (Phase 6) ==================== */

    /**
     * GET /api/auth/mfa/status (authenticated)
     * Whether the account currently has a second factor enrolled.
     */
    public function mfaStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'enabled' => $user->hasMfaEnabled(),
            'recovery_codes_remaining' => $user->hasMfaEnabled() ? count($user->mfa_recovery_codes ?? []) : 0,
            'enabled_at' => $user->mfa_enabled_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/auth/mfa/setup (authenticated)
     * Generates a TOTP secret + single-use recovery codes. The account stays
     * unprotected until POST /api/auth/mfa/enable confirms a working code.
     */
    public function mfaSetup(Request $request)
    {
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor sign-in is already active on this account.',
            ], 409);
        }

        $secret = $this->mfa->generateSecret();
        $recoveryCodes = $this->mfa->generateRecoveryCodes();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->mfa->hashRecoveryCodes($recoveryCodes),
            'mfa_enabled_at' => null,
        ])->save();

        $this->writeAudit($request, 'auth.mfa_setup', (string) $user->id, 'Two-factor enrolment started.', [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
        ]);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'otpauth_uri' => $this->mfa->otpauthUri($user->email, $secret),
            'recovery_codes' => $recoveryCodes,
            'message' => 'Add this account to your authenticator app, then confirm with a 6-digit code.',
        ]);
    }

    /**
     * POST /api/auth/mfa/enable (authenticated)
     * Confirms the enrolment with a live code and activates the second factor.
     */
    public function mfaEnable(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $user = $request->user();

        if (!$user->mfa_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Start the enrolment before activating it.',
            ], 409);
        }

        if (!$this->mfa->verify((string) $user->mfa_secret, $data['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'That code is not valid. Check your authenticator and try again.',
            ], 422);
        }

        $user->forceFill(['mfa_enabled_at' => now()])->save();

        // The current session just proved possession of the second factor.
        // Stamp THIS token so the very next request is not rejected by the
        // middleware (no logout/login round-trip required).
        $token = $request->user()->currentAccessToken();
        if ($token) {
            MfaVerification::stamp((int) $user->id, (int) $token->id);
        }

        $this->writeAudit($request, 'auth.mfa_enabled', (string) $user->id, 'Two-factor sign-in activated.', [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor sign-in is now active on this account.',
        ]);
    }

    /**
     * POST /api/auth/mfa/disable (authenticated)
     * Requires the current password plus a live code — a stolen session alone
     * cannot silently remove the second factor.
     */
    public function mfaDisable(Request $request)
    {
        $data = $request->validate([
            'password' => 'required|string',
            'code' => 'required|string|max:40',
        ]);

        $user = $request->user();

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Your password is not correct.',
            ], 422);
        }

        if ($user->hasMfaEnabled() && !$this->mfa->verify((string) $user->mfa_secret, $data['code'])
            && !$this->mfa->consumeRecoveryCode($user, $data['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'That code is not valid.',
            ], 422);
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
            'mfa_recovery_codes' => null,
        ])->save();
        // Disabling MFA must invalidate every outstanding verification stamp.
        MfaVerification::forgetAll((int) $user->id);

        $this->writeAudit($request, 'auth.mfa_disabled', (string) $user->id, 'Two-factor sign-in removed.', [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor sign-in has been removed from this account.',
        ]);
    }

    /**
     * POST /api/auth/mfa/verify (public, throttled)
     * Completes a pending sign-in with an authenticator code or a single-use
     * recovery code. The challenge is bound to one account, expires after
     * five minutes and dies after too many wrong codes.
     */
    public function mfaVerify(Request $request)
    {
        $data = $request->validate([
            'challenge' => 'required|string|min:20|max:120',
            'code' => 'required|string|max:40',
        ]);

        $key = $this->mfaChallengeKey($data['challenge']);
        $payload = Cache::get($key);

        if (!is_array($payload) || !isset($payload['user_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'This verification session expired. Please sign in again.',
            ], 401);
        }

        $user = User::find($payload['user_id']);
        if (!$user || !$user->hasMfaEnabled()) {
            Cache::forget($key);

            return response()->json([
                'success' => false,
                'message' => 'This verification session is no longer valid.',
            ], 401);
        }

        $verified = $this->mfa->verify((string) $user->mfa_secret, $data['code']);
        $usedRecovery = false;

        if (!$verified) {
            $usedRecovery = $this->mfa->consumeRecoveryCode($user, $data['code']);
        }

        if (!$verified && !$usedRecovery) {
            $attempts = (int) ($payload['attempts'] ?? 0) + 1;
            if ($attempts >= self::MFA_MAX_ATTEMPTS) {
                Cache::forget($key);
            } else {
                $payload['attempts'] = $attempts;
                Cache::put($key, $payload, now()->addSeconds(self::MFA_CHALLENGE_TTL));
            }

            $this->recordLoginAttempt($request, $user, $user->email, false, false, 'invalid_mfa_code');

            return response()->json([
                'success' => false,
                'message' => 'That verification code is not valid.',
            ], 422);
        }

        // The challenge is single-use: it is destroyed as soon as it succeeds.
        Cache::forget($key);

        // A recovery code is a "lost device" signal: revoke every other
        // verified session before issuing this one.
        if ($usedRecovery) {
            MfaVerification::forgetAll((int) $user->id);
        }

        // Create the token first, then stamp verification for THAT token only.
        $token = $user->createToken('auth-token');
        MfaVerification::stamp((int) $user->id, (int) $token->accessToken->id);
        $token = $token->plainTextToken;
        $this->recordLoginAttempt($request, $user, $user->email, true, true, $usedRecovery ? 'recovery_code' : 'totp');

        $this->writeAudit($request, 'auth.mfa_verified', (string) $user->id, 'Two-factor sign-in completed.', [
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'metadata' => ['method' => $usedRecovery ? 'recovery_code' : 'totp'],
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'mfa_used' => true,
            'user' => $this->userPayload($user, $request->header('X-Agency-Id')),
        ]);
    }

    /**
     * GET /api/auth/login-history (authenticated)
     * The account owner's own sign-in trail (IP, device, outcome).
     */
    public function loginHistory(Request $request)
    {
        $user = $request->user();

        $history = LoginHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (LoginHistory $row) => [
                'id' => $row->id,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'success' => $row->success,
                'mfa_used' => $row->mfa_used,
                'suspicious' => $row->suspicious,
                'reason' => $row->reason,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /* ==================== Internals ==================== */

    private function mfaChallengeKey(string $challenge): string
    {
        return 'auth:mfa:challenge:' . hash('sha256', $challenge);
    }

    private function createMfaChallenge(User $user): string
    {
        $challenge = bin2hex(random_bytes(24));

        Cache::put($this->mfaChallengeKey($challenge), [
            'user_id' => $user->id,
            'attempts' => 0,
        ], now()->addSeconds(self::MFA_CHALLENGE_TTL));

        return $challenge;
    }

    /**
     * Appends every sign-in attempt to the immutable login trail and flags
     * unusual ones (new IP address / new device within the last 30 days).
     */
    private function recordLoginAttempt(
        Request $request,
        ?User $user,
        string $email,
        bool $success,
        bool $mfaUsed = false,
        ?string $reason = null
    ): void {
        $ip = $request->ip();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 255);
        $suspicious = false;

        if ($success && $user !== null) {
            $previous = LoginHistory::where('user_id', $user->id)
                ->where('success', true)
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            if ($previous->isNotEmpty()) {
                $knownIps = $previous->pluck('ip_address')->filter()->unique()->all();
                $knownAgents = $previous->pluck('user_agent')->filter()->unique()->all();

                if ($ip && !in_array($ip, $knownIps, true)) {
                    $suspicious = true;
                    $reason = 'new_ip_address';
                } elseif ($userAgent && !in_array($userAgent, $knownAgents, true)) {
                    $suspicious = true;
                    $reason = 'new_device';
                }
            }
        }

        LoginHistory::create([
            'user_id' => $user?->id,
            'email' => mb_substr((string) $email, 0, 190),
            'ip_address' => $ip,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'success' => $success,
            'mfa_used' => $mfaUsed,
            'suspicious' => $suspicious,
            'reason' => $reason,
        ]);

        if ($suspicious && $user !== null) {
            $user->notify(new SuspiciousLoginDetected($reason ?? 'unknown', $ip, $userAgent));

            $this->writeAudit($request, 'auth.suspicious_login', (string) $user->id, 'Sign-in from a new IP address or device detected.', [
                'entity_type' => 'user',
                'entity_id' => (string) $user->id,
                'metadata' => ['ip' => $ip, 'reason' => $reason],
            ]);
        }
    }

    /**
     * Shared registration/login/me payload. Denied accounts expose no usable
     * permissions. Approved accounts keep the legacy matrix and permissions
     * for the selected agency; agencies lets the client pick another context.
     */
    public function userPayload(User $user, ?string $agencyId = null): array
    {
        $memberships = $user->memberAgencies();
        $access = $user->workspaceAccess($agencyId);
        $actingAgency = $user->resolveAgencyId($agencyId);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'dealer_id' => $user->dealer_id,
            'phone' => $user->phone,
            'permissions' => $access['allowed'] ? RolePermissions::forRole($user->role) : [],
            'enterprise_permissions' => $access['allowed']
                ? $user->permissionsFor($user->isPlatformUser() ? null : $actingAgency) : [],
            'agencies' => $memberships,
            'is_platform' => $user->isPlatformUser(),
            'workspace_access' => $access,
        ];
    }

}
