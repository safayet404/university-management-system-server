<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:DejaVu Sans,sans-serif; font-size:11px; color:#1f2937; }
  .header { background:#d97706; color:white; padding:20px 24px; margin-bottom:20px; }
  .header h1 { font-size:20px; font-weight:bold; }
  .header p  { font-size:10px; opacity:.85; margin-top:4px; }
  .content   { padding:0 24px 24px; }
  .summary   { display:flex; gap:12px; margin-bottom:20px; }
  .stat-card { flex:1; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px; text-align:center; }
  .stat-card .val { font-size:18px; font-weight:bold; color:#d97706; }
  .stat-card .lbl { font-size:9px; color:#6b7280; margin-top:2px; }
  h2 { font-size:13px; font-weight:bold; color:#374151; margin:16px 0 8px; border-bottom:2px solid #d97706; padding-bottom:4px; }
  table { width:100%; border-collapse:collapse; font-size:10px; }
  thead tr { background:#d97706; color:white; }
  thead th { padding:7px 8px; text-align:left; font-weight:600; }
  tbody tr:nth-child(even) { background:#fffbeb; }
  tbody td { padding:6px 8px; border-bottom:1px solid #e5e7eb; }
  .footer { margin-top:24px; text-align:center; font-size:9px; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:8px; }
</style>
</head>
<body>
<div class="header">
  <h1>UniCore — {{ $title }}</h1>
  <p>Semester: {{ $semester }} · Total Records: {{ $total }} · Generated {{ $generated_at }}</p>
</div>
<div class="content">
  <div class="summary">
    <div class="stat-card"><div class="val">{{ $total }}</div><div class="lbl">Total Records</div></div>
    <div class="stat-card"><div class="val">{{ $present }}</div><div class="lbl">Present</div></div>
    <div class="stat-card"><div class="val">{{ $total - $present }}</div><div class="lbl">Absent/Late</div></div>
    <div class="stat-card"><div class="val">{{ $total > 0 ? round(($present/$total)*100,1) : 0 }}%</div><div class="lbl">Rate</div></div>
  </div>

  <h2>Attendance Records</h2>
  <table>
    <thead><tr><th>#</th><th>Student</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($records->take(80) as $i => $r)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $r->student?->user?->name }}</td>
        <td>{{ $r->session?->course?->code }}</td>
        <td>{{ $r->session?->date?->format('d M Y') }}</td>
        <td>{{ ucfirst($r->status) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
<div class="footer">UniCore University Information Management System · Confidential</div>
</body>
</html>
