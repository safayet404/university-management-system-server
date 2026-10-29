<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Support\Institution;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use LogsPageVisit;

    // ── POST /api/auth/login ──────────────────────────────────
    // Supports login by: email, employee_id, or student_id
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required_without:email|string|nullable',
            'email'    => 'required_without:login|string|nullable',
            'password' => 'required|string',
        ]);

        $identifier = $request->login ?? $request->email;

        // Find user by email, employee_id, or student_id
        $user = User::with('roles.permissions')
            ->where('email', $identifier)
            ->orWhere('employee_id', $identifier)
            ->orWhere('student_id', $identifier)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is ' . $user->status . '. Please contact administrator.',
            ], 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'is_online'     => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        self::logVisit('auth', 'login', 'login', "User {$user->name} logged in");

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $this->formatUser($user),
        ]);
    }

    // ── GET /api/app-config ───────────────────────────────────
    // Public facts the frontend needs before sign-in
    public function appConfig()
    {
        return response()->json(['success' => true, 'data' => [
            'name'             => config('app.name'),
            'institution_type' => Institution::type(),
            'demo_roles'       => config('app.demo_roles'),
        ]]);
    }

    // ── GET /api/auth/demo-roles ──────────────────────────────
    public function demoRoles()
    {
        return response()->json(['success' => true, 'data' => config('app.demo_roles')]);
    }

    // ── POST /api/auth/demo-login ─────────────────────────────
    // Password-less, read-only session for visitors trying the demo
    public function demoLogin(Request $request)
    {
        $request->validate(['role' => ['required', 'string', Rule::in(array_diff(config('app.demo_roles'), ['super-admin']))]]);

        $user = User::role($request->role)->where('status', 'active')->orderBy('id')
            ->with('roles.permissions')->first();
        abort_unless($user, 404, 'No demo account for this role.');

        $token = $user->createToken('demo', ['demo'])->plainTextToken;

        self::logVisit('auth', 'login', 'login', "Demo login as {$user->name}");

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $this->formatUser($user) + ['is_demo' => true],
        ]);
    }

    // ── POST /api/auth/forgot-password ────────────────────────
    // Same answer whether or not the account exists, so the endpoint cannot be
    // used to discover which emails are registered
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        PasswordBroker::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email, a reset link is on its way.',
        ]);
    }

    // ── POST /api/auth/reset-password ─────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => ['required', 'string', 'confirmed', PasswordPolicy::rule()],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'must_change_password' => false])->save();
                $user->tokens()->delete(); // sign out every device
                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.'], 422);
        }

        self::logVisit('auth', 'password', 'password-reset', 'Password reset via email link');

        return response()->json(['success' => true, 'message' => 'Your password has been reset. You can now sign in.']);
    }

    // ── POST /api/auth/logout ─────────────────────────────────
    public function logout(Request $request)
    {
        self::logVisit('auth', 'logout', 'logout', "User {$request->user()->name} logged out");
        $request->user()->update(['is_online' => false]);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    // ── GET /api/auth/me ──────────────────────────────────────
    public function me(Request $request)
    {
        $user   = $request->user()->load('roles.permissions');
        $token  = $request->user()->currentAccessToken();
        $isDemo = $token instanceof PersonalAccessToken && in_array('demo', $token->abilities ?? [], true);
        return response()->json(['success' => true, 'user' => $this->formatUser($user) + ['is_demo' => $isDemo]]);
    }

    // ── PUT /api/auth/profile ─────────────────────────────────
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'phone'         => 'sometimes|nullable|string|max:20',
            'gender'        => 'sometimes|nullable|in:male,female,other',
            'date_of_birth' => 'sometimes|nullable|date',
            'address'       => 'sometimes|nullable|string',
            'city'          => 'sometimes|nullable|string|max:100',
            'country'       => 'sometimes|nullable|string|max:100',
        ]);

        $old = $user->only(array_keys($validated));
        $user->update($validated);
        self::logVisit('auth', 'profile', 'updated', "Profile updated", $old, $validated, User::class, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user'    => $this->formatUser($user->fresh()->load('roles.permissions')),
        ]);
    }

    // ── PUT /api/auth/change-password ─────────────────────────
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'confirmed', 'different:current_password', PasswordPolicy::rule()],
        ]);

        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => $request->password, 'must_change_password' => false]);

        // Sign out every other device that still holds the old credentials
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        self::logVisit('auth', 'profile', 'password-changed', "Password changed");
        return response()->json(['success' => true, 'message' => 'Password changed successfully.', 'user' => $this->formatUser($user->fresh())]);
    }

    // ── POST /api/auth/avatar ─────────────────────────────────
    public function uploadAvatar(Request $request)
    {
        abort_unless(config('app.avatar_uploads'), 503, 'Avatar uploads are disabled on this deployment.');

        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048']);
        $user = $request->user();
        if ($user->avatar) Storage::disk('public')->delete($user->avatar);
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);
        self::logVisit('auth', 'profile', 'avatar-updated', "Avatar updated");
        return response()->json(['success' => true, 'avatar_url' => $user->avatar_url]);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'avatar_url'    => $user->avatar_url,
            'status'        => $user->status,
            'gender'        => $user->gender,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'address'       => $user->address,
            'city'          => $user->city,
            'country'       => $user->country,
            'employee_id'   => $user->employee_id,
            'student_id'    => $user->student_id,
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $user->last_login_ip,
            'is_online'     => $user->is_online,
            'must_change_password' => (bool) $user->must_change_password,
            'roles'         => $user->getRoleNames(),
            'permissions'   => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
