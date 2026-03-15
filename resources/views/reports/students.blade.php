<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
  .header { background: #4f46e5; color: white; padding: 20px 24px; margin-bottom: 20px; }
  .header h1 { font-size: 20px; font-weight: bold; }
  .header p  { font-size: 10px; opacity: 0.85; margin-top: 4px; }
  .content { padding: 0 24px 24px; }
  .summary { display: flex; gap: 12px; margin-bottom: 20px; }
  .stat-card { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
  .stat-card .val { font-size: 22px; font-weight: bold; color: #4f46e5; }
  .stat-card .lbl { font-size: 9px; color: #6b7280; margin-top: 2px; }
  h2 { font-size: 13px; font-weight: bold; color: #374151; margin: 16px 0 8px; border-bottom: 2px solid #4f46e5; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  thead tr { background: #4f46e5; color: white; }
  thead th { padding: 7px 8px; text-align: left; font-weight: 600; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
  .badge { display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
  .badge-green  { background: #d1fae5; color: #065f46; }
  .badge-blue   { background: #dbeafe; color: #1e40af; }
  .badge-gray   { background: #f3f4f6; color: #6b7280; }
  .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
  <h1>UniCore — {{ $title }}</h1>
  <p>Generated on {{ $generated_at }} by {{ $generated_by }}</p>
</div>
<div class="content">

  <div class="summary">
    <div class="stat-card"><div class="val">{{ $total }}</div><div class="lbl">Total Students</div></div>
    <div class="stat-card"><div class="val">{{ $students->where('academic_status','regular')->count() }}</div><div class="lbl">Active</div></div>
    <div class="stat-card"><div class="val">{{ $students->filter(fn($s)=>$s->user?->gender==='male')->count() }}</div><div class="lbl">Male</div></div>
    <div class="stat-card"><div class="val">{{ $students->filter(fn($s)=>$s->user?->gender==='female')->count() }}</div><div class="lbl">Female</div></div>
  </div>

  <h2>By Department</h2>
  <table>
    <thead><tr><th>Department</th><th>Count</th></tr></thead>
    <tbody>
      @foreach($by_department as $row)
      <tr><td>{{ $row['name'] }}</td><td>{{ $row['count'] }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <h2>Student List</h2>
  <table>
    <thead>
      <tr><th>#</th><th>Student ID</th><th>Name</th><th>Dept</th><th>Program</th><th>Semester</th><th>CGPA</th><th>Status</th></tr>
    </thead>
    <tbody>
      @foreach($students->take(50) as $i => $s)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $s->student_id }}</td>
        <td>{{ $s->user?->name }}</td>
        <td>{{ $s->department?->code }}</td>
        <td>{{ $s->program?->code }}</td>
        <td>{{ $s->semester }}</td>
        <td>{{ $s->cgpa ?? '—' }}</td>
        <td><span class="badge badge-green">{{ $s->academic_status }}</span></td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
<div class="footer">UniCore University Information Management System · Confidential</div>
</body>
</html>
