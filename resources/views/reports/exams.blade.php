<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:DejaVu Sans,sans-serif; font-size:11px; color:#1f2937; }
  .header { background:#7c3aed; color:white; padding:20px 24px; margin-bottom:20px; }
  .header h1 { font-size:20px; font-weight:bold; }
  .header p  { font-size:10px; opacity:.85; margin-top:4px; }
  .content   { padding:0 24px 24px; }
  .summary   { display:flex; gap:12px; margin-bottom:20px; }
  .stat-card { flex:1; background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:12px; text-align:center; }
  .stat-card .val { font-size:18px; font-weight:bold; color:#7c3aed; }
  .stat-card .lbl { font-size:9px; color:#6b7280; margin-top:2px; }
  h2 { font-size:13px; font-weight:bold; color:#374151; margin:16px 0 8px; border-bottom:2px solid #7c3aed; padding-bottom:4px; }
  table { width:100%; border-collapse:collapse; font-size:10px; }
  thead tr { background:#7c3aed; color:white; }
  thead th { padding:7px 8px; text-align:left; font-weight:600; }
  tbody tr:nth-child(even) { background:#faf5ff; }
  tbody td { padding:6px 8px; border-bottom:1px solid #e5e7eb; }
  .badge { display:inline-block; padding:2px 6px; border-radius:9999px; font-size:9px; font-weight:600; }
  .footer { margin-top:24px; text-align:center; font-size:9px; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:8px; }
</style>
</head>
<body>
<div class="header">
  <h1>UniCore — {{ $title }}</h1>
  <p>Semester: {{ $semester }} · Avg GPA: {{ $avg_gpa }} · Generated {{ $generated_at }}</p>
</div>
<div class="content">
  <div class="summary">
    <div class="stat-card"><div class="val">{{ $grades->count() }}</div><div class="lbl">Total Grades</div></div>
    <div class="stat-card"><div class="val">{{ $avg_gpa }}</div><div class="lbl">Avg GPA</div></div>
    <div class="stat-card"><div class="val">{{ $grades->where('grade_point','>=',2.0)->count() }}</div><div class="lbl">Passed</div></div>
    <div class="stat-card"><div class="val">{{ $grades->where('grade_point','>=',3.75)->count() }}</div><div class="lbl">Distinction</div></div>
  </div>

  <h2>Grade Results</h2>
  <table>
    <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Course</th><th>Marks</th><th>Grade</th><th>GPA</th></tr></thead>
    <tbody>
      @foreach($grades->take(60) as $i => $g)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $g->student?->user?->name }}</td>
        <td>{{ $g->student?->student_id }}</td>
        <td>{{ $g->course?->code }}</td>
        <td>{{ $g->total_marks ?? '—' }}</td>
        <td><strong>{{ $g->grade_letter ?? '—' }}</strong></td>
        <td>{{ $g->grade_point ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
<div class="footer">UniCore University Information Management System · Confidential</div>
</body>
</html>
