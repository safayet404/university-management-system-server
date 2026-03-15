<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use LogsPageVisit;

    public function index(Request $request)
    {
        self::logVisit('departments', 'list', 'visited', 'Visited departments list');

        $query = Department::withCount(['programs', 'studentProfiles'])->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        if ($request->filled('status')) $query->where('status', $request->status);

        $depts = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->get('per_page', 15));

        if ($request->boolean('all')) {
            return response()->json(['success' => true, 'data' => $depts]);
        }

        return response()->json([
            'success'    => true,
            'data'       => $depts->items(),
            'pagination' => [
                'total'        => $depts->total(),
                'current_page' => $depts->currentPage(),
                'last_page'    => $depts->lastPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $dept = Department::withCount(['programs', 'studentProfiles'])->with('programs')->findOrFail($id);
        self::logVisit('departments', 'view', 'visited', "Viewed department: {$dept->name}", [], [], Department::class, $dept->id);
        return response()->json(['success' => true, 'data' => $dept]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:departments,code',
            'short_name' => 'nullable|string|max:50',
            'description'=> 'nullable|string',
            'head_name'  => 'nullable|string|max:255',
            'email'      => 'nullable|email',
            'phone'      => 'nullable|string|max:20',
            'building'   => 'nullable|string|max:100',
            'room'       => 'nullable|string|max:50',
            'website'    => 'nullable|string|max:255',
            'status'     => 'nullable|in:active,inactive',
        ]);

        $dept = Department::create($validated);
        self::logVisit('departments', 'create', 'created', "Created department: {$dept->name}", [], $validated, Department::class, $dept->id);

        return response()->json(['success' => true, 'message' => 'Department created.', 'data' => $dept], 201);
    }

    public function update(Request $request, $id)
    {
        $dept = Department::findOrFail($id);
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'code'       => 'sometimes|string|max:20|unique:departments,code,' . $id,
            'short_name' => 'nullable|string|max:50',
            'description'=> 'nullable|string',
            'head_name'  => 'nullable|string|max:255',
            'email'      => 'nullable|email',
            'phone'      => 'nullable|string|max:20',
            'building'   => 'nullable|string|max:100',
            'room'       => 'nullable|string|max:50',
            'website'    => 'nullable|string|max:255',
            'status'     => 'nullable|in:active,inactive',
        ]);

        $old = $dept->only(array_keys($validated));
        $dept->update($validated);
        self::logVisit('departments', 'edit', 'updated', "Updated department: {$dept->name}", $old, $validated, Department::class, $dept->id);

        return response()->json(['success' => true, 'message' => 'Department updated.', 'data' => $dept->fresh()]);
    }

    public function destroy($id)
    {
        $dept = Department::findOrFail($id);
        if ($dept->studentProfiles()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete department with enrolled students.'], 422);
        }
        self::logVisit('departments', 'delete', 'deleted', "Deleted department: {$dept->name}", [], [], Department::class, $dept->id);
        $dept->delete();
        return response()->json(['success' => true, 'message' => 'Department deleted.']);
    }
}
