<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    use LogsPageVisit;

    public function index(Request $request)
    {
        self::logVisit('programs', 'list', 'visited', 'Visited programs list');

        $query = Program::with('department')->withCount('studentProfiles')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('degree_type'))   $query->where('degree_type', $request->degree_type);
        if ($request->filled('status'))        $query->where('status', $request->status);

        $programs = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->get('per_page', 15));

        if ($request->boolean('all')) {
            return response()->json(['success' => true, 'data' => $programs]);
        }

        return response()->json([
            'success'    => true,
            'data'       => $programs->items(),
            'pagination' => [
                'total'        => $programs->total(),
                'current_page' => $programs->currentPage(),
                'last_page'    => $programs->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id'          => 'required|exists:departments,id',
            'name'                   => 'required|string|max:255',
            'code'                   => 'required|string|max:20|unique:programs,code',
            'degree_type'            => 'required|in:bachelor,master,diploma,phd,certificate',
            'duration_years'         => 'nullable|integer|min:1|max:10',
            'total_credits'          => 'nullable|integer|min:1',
            'dept_code'              => 'nullable|integer',
            'description'            => 'nullable|string',
            'status'                 => 'nullable|in:active,inactive',
        ]);

        $program = Program::create($validated);
        self::logVisit('programs', 'create', 'created', "Created program: {$program->name}", [], $validated, Program::class, $program->id);

        return response()->json(['success' => true, 'message' => 'Program created.', 'data' => $program->load('department')], 201);
    }

    public function update(Request $request, $id)
    {
        $program = Program::findOrFail($id);
        $validated = $request->validate([
            'department_id'  => 'sometimes|exists:departments,id',
            'name'           => 'sometimes|string|max:255',
            'code'           => 'sometimes|string|max:20|unique:programs,code,' . $id,
            'degree_type'    => 'sometimes|in:bachelor,master,diploma,phd,certificate',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'total_credits'  => 'nullable|integer|min:1',
            'dept_code'      => 'nullable|integer',
            'description'    => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
        ]);

        $old = $program->only(array_keys($validated));
        $program->update($validated);
        self::logVisit('programs', 'edit', 'updated', "Updated program: {$program->name}", $old, $validated, Program::class, $program->id);

        return response()->json(['success' => true, 'message' => 'Program updated.', 'data' => $program->fresh()->load('department')]);
    }

    public function destroy($id)
    {
        $program = Program::findOrFail($id);
        if ($program->studentProfiles()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete program with enrolled students.'], 422);
        }
        self::logVisit('programs', 'delete', 'deleted', "Deleted program: {$program->name}", [], [], Program::class, $program->id);
        $program->delete();
        return response()->json(['success' => true, 'message' => 'Program deleted.']);
    }
}
