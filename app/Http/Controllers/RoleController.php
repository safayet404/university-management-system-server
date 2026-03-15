<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/roles ────────────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('roles', 'list', 'visited', 'Visited roles list');

        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn($role) => [
                'id'              => $role->id,
                'name'            => $role->name,
                'users_count'     => \DB::table('model_has_roles')->where('role_id', $role->id)->count(),
                'permissions'     => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'created_at'      => $role->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['success' => true, 'data' => $roles]);
    }

    // ── POST /api/roles ───────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name|max:100']);

        $role = Role::create(['name' => strtolower($request->name), 'guard_name' => 'web']);

        self::logVisit('roles', 'create', 'created', "Role '{$role->name}' created", [], ['name' => $role->name], Role::class, $role->id);

        return response()->json(['success' => true, 'message' => 'Role created.', 'data' => $role]);
    }

    // ── PUT /api/roles/{id} ───────────────────────────────────
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate(['name' => 'required|string|unique:roles,name,' . $id . '|max:100']);

        $old = ['name' => $role->name];
        $role->update(['name' => strtolower($request->name)]);

        self::logVisit('roles', 'edit', 'updated', "Role renamed to '{$role->name}'", $old, ['name' => $role->name], Role::class, $role->id);

        return response()->json(['success' => true, 'message' => 'Role updated.', 'data' => $role]);
    }

    // ── DELETE /api/roles/{id} ────────────────────────────────
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['super-admin', 'admin'])) {
            return response()->json(['success' => false, 'message' => 'Cannot delete system roles.'], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete role with assigned users.'], 422);
        }

        self::logVisit('roles', 'delete', 'deleted', "Role '{$role->name}' deleted", ['name' => $role->name], [], Role::class, $role->id);

        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    // ── GET /api/roles/{id}/permissions ───────────────────────
    public function permissions($id)
    {
        $role        = Role::with('permissions')->findOrFail($id);
        $allPerms    = Permission::orderBy('name')->get();

        // Group permissions by module
        $modules = [];
        foreach ($allPerms as $perm) {
            $parts  = explode('.', $perm->name);
            $module = $parts[0] ?? 'general';
            $action = $parts[1] ?? $perm->name;

            if (!isset($modules[$module])) {
                $modules[$module] = ['module' => $module, 'permissions' => []];
            }
            $modules[$module]['permissions'][] = [
                'id'      => $perm->id,
                'name'    => $perm->name,
                'action'  => $action,
                'granted' => $role->permissions->contains('name', $perm->name),
            ];
        }

        self::logVisit('roles', 'permissions', 'visited', "Viewed permissions for role '{$role->name}'");

        return response()->json([
            'success' => true,
            'role'    => ['id' => $role->id, 'name' => $role->name],
            'modules' => array_values($modules),
        ]);
    }

    // ── PATCH /api/roles/{id}/permissions ─────────────────────
    public function syncPermissions(Request $request, $id)
    {
        $request->validate(['permissions' => 'required|array']);

        $role    = Role::findOrFail($id);
        $old     = $role->permissions->pluck('name')->toArray();

        $granted = collect($request->permissions)
            ->filter(fn($v) => $v === true)
            ->keys()
            ->toArray();

        $role->syncPermissions($granted);

        $new      = $role->fresh()->permissions->pluck('name')->toArray();
        $added    = array_diff($new, $old);
        $revoked  = array_diff($old, $new);

        self::logVisit(
            'roles', 'permissions', 'sync',
            "Permissions updated for role '{$role->name}'",
            ['permissions' => $old],
            ['permissions' => $new, 'granted' => $added, 'revoked' => $revoked],
            Role::class, $role->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated.',
            'granted' => count($added),
            'revoked' => count($revoked),
        ]);
    }

    // ── POST /api/roles/{id}/clone ────────────────────────────
    public function clone(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|unique:roles,name|max:100']);

        $original = Role::with('permissions')->findOrFail($id);
        $clone    = Role::create(['name' => strtolower($request->name), 'guard_name' => 'web']);
        $clone->syncPermissions($original->permissions->pluck('name')->toArray());

        self::logVisit('roles', 'clone', 'cloned', "Role '{$original->name}' cloned as '{$clone->name}'", [], ['name' => $clone->name, 'cloned_from' => $original->name], Role::class, $clone->id);

        return response()->json(['success' => true, 'message' => 'Role cloned.', 'data' => $clone]);
    }

    // ── GET /api/roles/{id}/history ───────────────────────────
    public function history(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $history = \App\Models\PageVisit::with('user')
            ->where('model_type', Role::class)
            ->where('model_id', $id)
            ->latest()
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data'    => $history->items(),
            'pagination' => [
                'total'        => $history->total(),
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
            ],
        ]);
    }
}
