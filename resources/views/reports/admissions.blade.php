<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:DejaVu Sans,sans-serif; font-size:11px; color:#1f2937; }
  .header { background:#0891b2; color:white; padding:20px 24px; margin-bottom:20px; }
  .header h1 { font-size:20px; font-weight:bold; }
  .header p  { font-size:10px; opacity:.85; margin-top:4px; }
  .content   { padding:0 24px 24px; }
  .summary   { display:flex; gap:12px; margin-bottom:20px; }
  .stat-card { flex:1; background:#ecfeff; border:1px solid #a5f3fc; border-radius:8px; padding:12px; text-align:center; }
  .stat-card .val { font-size:18px; font-weight:bold; color:#0891b2; }
  .stat-card .lbl { font-size:9px; color:#6b7280; margin-top:2px; }
  h2 { font-size:13px; font-weight:bold; color:#374151; margin:16px 0 8px; border-bottom:2px solid #0891b2; padding-bottom:4px; }
  table { width:100%; border-collapse:collapse; font-size:10px; }
  thead tr { background:#0891b2; color:white; }
  thead th { padding:7px 8px; text-align:left; font-weight:600; }
  tbody tr:nth-child(even) { background:#ecfeff; }
  tbody td { padding:6px 8px; border-bottom:1px solid #e5e7eb; }
  .badge { display:inline-block; padding:2px 6px; border-radius:9999px; font-size:9px; font-weight:600; }
  .badge-green  { background:#d1fae5; color:#065f46; }
  .badge-red    { background:#fee2e2; color:#991b1b; }
  .badge-yellow { background:#fef9c3; color:#854d0e; }
  .badge-blue   { background:#dbeafe; color:#1e40af; }
  .footer { margin-top:24px; text-align:center; font-size:9px; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:8px; }
</style>
</head>
<body>
<div class="header">
  <h1>UniCore — {{ $title }}</h1>
  <p>Year: {{ $year }} · Total: {{ $total }} · Generated {{ $generated_at }}</p>
</div>
<div class="content">
  <div class="summary">
    <div class="stat-card"><div class="val">{{ $total }}</div><div class="lbl">Applications</div></div>
    <div class="stat-card"><div class="val">{{ $admissions->whereIn('status',['accepted','enrolled'])->count() }}</div><div class="lbl">Accepted</div></div>
    <div class="stat-card"><div class="val">{{ $admissions->where('status','enrolled')->count() }}</div><div class="lbl">Enrolled</div></div>
    <div class="stat-card"><div class="val">{{ $admissions->where('status','rejected')->count() }}</div><div class="lbl">Rejected</div></div>
  </div>

  <h2>Applications List</h2>
  <table>
    <thead><tr><th>#</th><th>App No.</th><th>Name</th><th>Dept</th><th>HSC GPA</th><th>Merit</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($admissions->take(60) as $i => $a)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $a->application_number }}</td>
        <td>{{ $a->first_name }} {{ $a->last_name }}</td>
        <td>{{ $a->department?->code }}</td>
        <td>{{ $a->hsc_gpa }}</td>
        <td>{{ $a->merit_score }}</td>
        <td>
          @php $cls = match($a->status) { 'accepted','enrolled'=>'badge-green','rejected'=>'badge-red','shortlisted'=>'badge-yellow',default=>'badge-blue' }; @endphp
          <span class="badge {{ $cls }}">{{ ucfirst($a->status) }}</span>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
<div class="footer">UniCore University Information Management System · Confidential</div>
</body>
</html>
