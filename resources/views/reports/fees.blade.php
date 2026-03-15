<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size:11px; color:#1f2937; }
  .header { background:#059669; color:white; padding:20px 24px; margin-bottom:20px; }
  .header h1 { font-size:20px; font-weight:bold; }
  .header p { font-size:10px; opacity:.85; margin-top:4px; }
  .content { padding:0 24px 24px; }
  .summary { display:flex; gap:12px; margin-bottom:20px; }
  .stat-card { flex:1; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; text-align:center; }
  .stat-card .val { font-size:18px; font-weight:bold; color:#059669; }
  .stat-card .lbl { font-size:9px; color:#6b7280; margin-top:2px; }
  h2 { font-size:13px; font-weight:bold; color:#374151; margin:16px 0 8px; border-bottom:2px solid #059669; padding-bottom:4px; }
  table { width:100%; border-collapse:collapse; font-size:10px; }
  thead tr { background:#059669; color:white; }
  thead th { padding:7px 8px; text-align:left; font-weight:600; }
  tbody tr:nth-child(even) { background:#f0fdf4; }
  tbody td { padding:6px 8px; border-bottom:1px solid #e5e7eb; }
  .text-right { text-align:right; }
  .badge { display:inline-block; padding:2px 6px; border-radius:9999px; font-size:9px; font-weight:600; }
  .badge-green { background:#d1fae5; color:#065f46; }
  .badge-red   { background:#fee2e2; color:#991b1b; }
  .badge-yellow{ background:#fef9c3; color:#854d0e; }
  .footer { margin-top:24px; text-align:center; font-size:9px; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:8px; }
</style>
</head>
<body>
<div class="header">
  <h1>UniCore — {{ $title }}</h1>
  <p>Semester: {{ $semester }} · Generated on {{ $generated_at }} by {{ $generated_by }}</p>
</div>
<div class="content">
  <div class="summary">
    <div class="stat-card"><div class="val">৳{{ number_format($total_invoiced,0) }}</div><div class="lbl">Total Invoiced</div></div>
    <div class="stat-card"><div class="val">৳{{ number_format($total_collected,0) }}</div><div class="lbl">Collected</div></div>
    <div class="stat-card"><div class="val">৳{{ number_format($total_invoiced - $total_collected,0) }}</div><div class="lbl">Pending</div></div>
    <div class="stat-card"><div class="val">{{ $invoices->count() }}</div><div class="lbl">Invoices</div></div>
  </div>

  <h2>Fee Collection by Student</h2>
  <table>
    <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Fee Type</th><th class="text-right">Amount</th><th class="text-right">Paid</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($invoices->take(60) as $i => $inv)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>{{ $inv->student?->user?->name }}</td>
        <td>{{ $inv->student?->student_id }}</td>
        <td>{{ ucfirst($inv->fee_type) }}</td>
        <td class="text-right">৳{{ number_format($inv->amount,0) }}</td>
        <td class="text-right">৳{{ number_format($inv->paid_amount,0) }}</td>
        <td>
          <span class="badge {{ $inv->status==='paid' ? 'badge-green' : ($inv->status==='overdue' ? 'badge-red' : 'badge-yellow') }}">
            {{ ucfirst($inv->status) }}
          </span>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
<div class="footer">UniCore University Information Management System · Confidential</div>
</body>
</html>
