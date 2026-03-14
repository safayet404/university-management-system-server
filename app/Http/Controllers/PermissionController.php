<?php

namespace App\Http\Controllers;

use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/permissions ──────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('permissions', 'list', 'visited', 'Visited permissions list');

        $query = Permission::orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('module')) {
            $query->where('name', 'like', $request->module . '.%');
        }

        $perms = $query->paginate($request->get('per_page', 20));

        // Group by module for display
        $grouped = collect($perms->items())->groupBy(function ($p) {
            return explode('.', $p->name)[0] ?? 'general';
        });

        return response()->json([
            'success'    => true,
            'data'       => $perms->items(),
            'grouped'    => $grouped,
            'pagination' => [
                'total'        => $perms->total(),
                'current_page' => $perms->currentPage(),
                'last_page'    => $perms->lastPage(),
            ],
        ]);
    }

    // ── POST /api/permissions ─────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name|max:100|regex:/^[a-z0-9\-\.]+$/',
        ], [
            'name.regex' => 'Permission name must be lowercase with dots and dashes only (e.g. student.create)',
        ]);

        $perm = Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        self::logVisit('permissions', 'create', 'created', "Permission '{$perm->name}' created", [], ['name' => $perm->name], Permission::class, $perm->id);

        return response()->json(['success' => true, 'message' => 'Permission created.', 'data' => $perm]);
    }

    // ── PUT /api/permissions/{id} ─────────────────────────────
    public function update(Request $request, $id)
    {
        $perm = Permission::findOrFail($id);
        $request->validate(['name' => 'required|string|unique:permissions,name,' . $id . '|max:100']);

        $old = $perm->name;
        $perm->update(['name' => $request->name]);

        self::logVisit('permissions', 'edit', 'updated', "Permission renamed from '{$old}' to '{$perm->name}'", ['name' => $old], ['name' => $perm->name], Permission::class, $perm->id);

        return response()->json(['success' => true, 'message' => 'Permission updated.', 'data' => $perm]);
    }

    // ── DELETE /api/permissions/{id} ──────────────────────────
    public function destroy($id)
    {
        $perm = Permission::findOrFail($id);

        self::logVisit('permissions', 'delete', 'deleted', "Permission '{$perm->name}' deleted", ['name' => $perm->name], [], Permission::class, $perm->id);

        $perm->delete();

        return response()->json(['success' => true, 'message' => 'Permission deleted.']);
    }

    // ── POST /api/permissions/bulk ────────────────────────────
    // Create multiple permissions at once (e.g. all CRUD for a module)
    public function bulkCreate(Request $request)
    {
        $request->validate([
            'module'  => 'required|string|max:50',
            'actions' => 'required|array',
            'actions.*' => 'string|in:read,create,edit,delete,export,import,approve,reject',
        ]);

        $created = [];
        foreach ($request->actions as $action) {
            $name = strtolower($request->module) . '.' . $action;
            if (!Permission::where('name', $name)->exists()) {
                $created[] = Permission::create(['name' => $name, 'guard_name' => 'web'])->name;
            }
        }

        self::logVisit('permissions', 'bulk-create', 'created', "Bulk created permissions for module '{$request->module}'", [], ['created' => $created]);

        return response()->json([
            'success' => true,
            'message' => count($created) . ' permissions created.',
            'created' => $created,
        ]);
    }
}
