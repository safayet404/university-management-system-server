<?php

namespace App\Http\Controllers;

use App\Models\GroupSubject;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Subjects of a school's classes, or of a coaching centre's batches. */
class SubjectController extends Controller
{
    // GET /api/subjects
    public function index(Request $request)
    {
        $subjects = Subject::with('classLevel')
            ->when($request->filled('class_level_id'), fn ($q) => $q->where('class_level_id', $request->class_level_id))
            ->when($request->filled('stream'), fn ($q) => $q->where(fn ($w) => $w->whereNull('stream')->orWhere('stream', $request->stream)))
            ->get()
            ->sortBy(fn ($s) => [$s->classLevel?->sort_order ?? 999, $s->sort_order, $s->name])
            ->values();

        return response()->json(['success' => true, 'data' => $subjects->map(fn ($s) => $this->format($s))]);
    }

    // POST /api/subjects
    public function store(Request $request)
    {
        $subject = Subject::create($request->validate($this->rules()));

        return response()->json(['success' => true, 'message' => 'Subject added.', 'data' => $this->format($subject->load('classLevel'))], 201);
    }

    // PUT /api/subjects/{id}
    public function update(Request $request, $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->update($request->validate($this->rules($subject->id)));

        return response()->json(['success' => true, 'message' => 'Subject updated.', 'data' => $this->format($subject->fresh('classLevel'))]);
    }

    // DELETE /api/subjects/{id}
    public function destroy($id)
    {
        $subject = Subject::findOrFail($id);
        if (GroupSubject::where('subject_id', $subject->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'This subject is taught in a section or batch. Remove it there first.'], 422);
        }
        $subject->delete();

        return response()->json(['success' => true, 'message' => 'Subject deleted.']);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name'           => 'required|string|max:100',
            'code'           => ['required', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($ignoreId)],
            'class_level_id' => 'nullable|exists:class_levels,id',
            'stream'         => 'nullable|in:science,arts,commerce',
            'full_marks'     => 'required|integer|min:1|max:1000',
            'pass_marks'     => 'required|integer|min:0|lte:full_marks',
            'is_optional'    => 'boolean',
            'sort_order'     => 'nullable|integer|min:0',
        ];
    }

    private function format(Subject $s): array
    {
        return [
            'id'          => $s->id,
            'name'        => $s->name,
            'code'        => $s->code,
            'class_level' => $s->classLevel ? ['id' => $s->classLevel->id, 'name' => $s->classLevel->name] : null,
            'stream'      => $s->stream,
            'full_marks'  => $s->full_marks,
            'pass_marks'  => $s->pass_marks,
            'is_optional' => $s->is_optional,
            'sort_order'  => $s->sort_order,
        ];
    }
}
