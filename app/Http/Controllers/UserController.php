<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/users ────────────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('users', 'list', 'visited', 'Visited users list');

        $query = User::with('roles')
            ->withTrashed($request->boolean('with_deleted'))
            ->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(
                fn($q) => $q
                    ->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('employee_id', 'like', "%{$s}%")
                    ->orWhere('student_id', 'like', "%{$s}%")
            );
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => collect($users->items())->map(fn($u) => $this->formatUser($u)),
            'pagination' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    // ── GET /api/users/{id} ───────────────────────────────────
    public function show($id)
    {
        $user = User::with('roles.permissions')->withTrashed()->findOrFail($id);

        self::logVisit('users', 'view', 'visited', "Viewed user: {$user->name}", [], [], User::class, $user->id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user, true),
        ]);
    }

    // ── POST /api/users ───────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'phone'         => 'nullable|string|max:20',
            'role'          => 'required|string|exists:roles,name',
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'employee_id'   => 'nullable|string|unique:users,employee_id',
            'student_id'    => 'nullable|string|unique:users,student_id',
            'status'        => 'nullable|in:active,inactive,suspended',
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'status'   => $validated['status'] ?? 'active',
        ]);

        $user->assignRole($validated['role']);

        self::logVisit('users', 'create', 'created', "Created user: {$user->name}", [], $this->formatUser($user), User::class, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data'    => $this->formatUser($user->load('roles')),
        ], 201);
    }

    // ── PUT /api/users/{id} ───────────────────────────────────
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|unique:users,email,' . $id,
            'phone'         => 'nullable|string|max:20',
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'employee_id'   => 'nullable|string|unique:users,employee_id,' . $id,
            'student_id'    => 'nullable|string|unique:users,student_id,' . $id,
            'status'        => 'nullable|in:active,inactive,suspended',
        ]);

        $old = $user->only(array_keys($validated));
        $user->update($validated);

        // Update role if provided
        if ($request->filled('role')) {
            $request->validate(['role' => 'string|exists:roles,name']);
            $user->syncRoles([$request->role]);
        }

        self::logVisit('users', 'edit', 'updated', "Updated user: {$user->name}", $old, $validated, User::class, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data'    => $this->formatUser($user->fresh()->load('roles')),
        ]);
    }

    // ── DELETE /api/users/{id} ────────────────────────────────
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete your own account.'], 422);
        }

        if ($user->hasRole('super-admin')) {
            return response()->json(['success' => false, 'message' => 'Cannot delete super admin.'], 422);
        }

        self::logVisit('users', 'delete', 'deleted', "Deleted user: {$user->name}", $this->formatUser($user), [], User::class, $user->id);

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
    }

    // ── POST /api/users/{id}/restore ─────────────────────────
    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        self::logVisit('users', 'restore', 'restored', "Restored user: {$user->name}", [], [], User::class, $user->id);

        return response()->json(['success' => true, 'message' => 'User restored successfully.']);
    }

    // ── PATCH /api/users/{id}/status ─────────────────────────
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,inactive,suspended']);

        $user    = User::findOrFail($id);
        $old     = $user->status;
        $user->update(['status' => $request->status]);

        // Revoke all tokens if suspended
        if ($request->status === 'suspended') {
            $user->tokens()->delete();
        }

        self::logVisit('users', 'status', 'status-changed', "User {$user->name} status changed from {$old} to {$request->status}", ['status' => $old], ['status' => $request->status], User::class, $user->id);

        return response()->json([
            'success' => true,
            'message' => "User status changed to {$request->status}.",
            'status'  => $user->status,
        ]);
    }

    // ── PATCH /api/users/{id}/role ────────────────────────────
    public function updateRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|string|exists:roles,name']);

        $user    = User::findOrFail($id);
        $oldRole = $user->getRoleNames()->first();
        $user->syncRoles([$request->role]);

        self::logVisit('users', 'role', 'role-changed', "User {$user->name} role changed from {$oldRole} to {$request->role}", ['role' => $oldRole], ['role' => $request->role], User::class, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'User role updated.',
            'roles'   => $user->getRoleNames(),
        ]);
    }

    // ── POST /api/users/{id}/avatar ───────────────────────────
    public function uploadAvatar(Request $request, $id)
    {
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        $user = User::findOrFail($id);

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        self::logVisit('users', 'avatar', 'avatar-updated', "Avatar updated for user: {$user->name}", [], [], User::class, $user->id);

        return response()->json([
            'success'    => true,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    // ── POST /api/users/{id}/reset-password ───────────────────
    public function resetPassword(Request $request, $id)
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);

        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete(); // Force re-login

        self::logVisit('users', 'password', 'password-reset', "Password reset for user: {$user->name}", [], [], User::class, $user->id);

        return response()->json(['success' => true, 'message' => 'Password reset successfully.']);
    }

    // ── POST /api/users/bulk-action ───────────────────────────
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action'   => 'required|in:activate,deactivate,suspend,delete',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $users  = User::whereIn('id', $request->user_ids)
            ->where('id', '!=', auth()->id())
            ->get();

        $count = 0;
        foreach ($users as $user) {
            match ($request->action) {
                'activate'   => $user->update(['status' => 'active']),
                'deactivate' => $user->update(['status' => 'inactive']),
                'suspend'    => tap($user->update(['status' => 'suspended']), fn() => $user->tokens()->delete()),
                'delete'     => $user->delete(),
            };
            $count++;
        }

        self::logVisit('users', 'bulk', 'bulk-' . $request->action, "Bulk {$request->action} on {$count} users", [], ['action' => $request->action, 'count' => $count]);

        return response()->json([
            'success' => true,
            'message' => "{$count} users {$request->action}d successfully.",
        ]);
    }

    // ── GET /api/users/export ─────────────────────────────────
    public function export(Request $request)
    {
        self::logVisit('users', 'export', 'exported', 'Exported users list');

        return Excel::download(new UsersExport($request->all()), 'users-' . now()->format('Y-m-d') . '.xlsx');
    }

    // ── GET /api/users/stats ──────────────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'      => User::count(),
                'active'     => User::where('status', 'active')->count(),
                'inactive'   => User::where('status', 'inactive')->count(),
                'suspended'  => User::where('status', 'suspended')->count(),
                'online'     => User::where('is_online', true)->count(),
                'by_role'    => \Spatie\Permission\Models\Role::withCount('users')->get()->map(fn($r) => [
                    'role'  => $r->name,
                    'count' => $r->users_count,
                ]),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
            ],
        ]);
    }

    // ── GET /api/users/{id}/activity ──────────────────────────
    public function activity(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $logs = \App\Models\PageVisit::where('user_id', $id)
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $logs->items(),
            'pagination' => [
                'total'        => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatUser(User $user, bool $detailed = false): array
    {
        $data = [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'avatar_url'    => $user->avatar_url,
            'status'        => $user->status,
            'gender'        => $user->gender,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'city'          => $user->city,
            'country'       => $user->country,
            'employee_id'   => $user->employee_id,
            'student_id'    => $user->student_id,
            'is_online'     => $user->is_online,
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $user->last_login_ip,
            'roles'         => $user->getRoleNames(),
            'created_at'    => $user->created_at?->format('Y-m-d H:i:s'),
            'deleted_at'    => $user->deleted_at?->format('Y-m-d H:i:s'),
        ];

        if ($detailed) {
            $data['address']     = $user->address;
            $data['permissions'] = $user->getAllPermissions()->pluck('name');
        }

        return $data;
    }
}
