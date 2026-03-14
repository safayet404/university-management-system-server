<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use LogsPageVisit;

    // ── POST /api/auth/login ──────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('roles.permissions')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is ' . $user->status . '. Please contact administrator.',
            ], 403);
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'is_online'     => true,
        ]);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Log login
        self::logVisit('auth', 'login', 'login', "User {$user->name} logged in");

        return response()->json([
            'success'     => true,
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $this->formatUser($user),
        ]);
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
        $user = $request->user()->load('roles.permissions');

        return response()->json([
            'success' => true,
            'user'    => $this->formatUser($user),
        ]);
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
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update(['password' => $request->password]);

        self::logVisit('auth', 'profile', 'password-changed', "Password changed");

        return response()->json(['success' => true, 'message' => 'Password changed successfully.']);
    }

    // ── POST /api/auth/avatar ─────────────────────────────────
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048']);

        $user = $request->user();

        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        self::logVisit('auth', 'profile', 'avatar-updated', "Avatar updated");

        return response()->json([
            'success'    => true,
            'avatar_url' => $user->avatar_url,
        ]);
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
            'roles'         => $user->getRoleNames(),
            'permissions'   => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
