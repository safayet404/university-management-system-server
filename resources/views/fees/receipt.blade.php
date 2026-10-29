<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 24px 28px; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; }
  .header { text-align: center; border-bottom: 3px solid #047857; padding-bottom: 8px; margin-bottom: 12px; }
  .header .name { font-size: 18px; font-weight: bold; color: #065f46; }
  .header .title { display: inline-block; margin-top: 6px; background: #047857; color: #fff; font-size: 10px; font-weight: bold; padding: 3px 14px; border-radius: 10px; letter-spacing: 1px; }
  table { width: 100%; border-collapse: collapse; }
  .info td { padding: 4px 4px; }
  .info .lbl { color: #6b7280; width: 30%; }
  .info .val { font-weight: bold; }
  .lines { margin-top: 12px; }
  .lines th { background: #047857; color: #fff; padding: 6px; text-align: left; font-size: 10px; }
  .lines th.r, .lines td.r { text-align: right; }
  .lines td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
  .paid td { font-size: 13px; font-weight: bold; color: #065f46; border-top: 2px solid #047857; border-bottom: none; }
  .status { margin-top: 10px; text-align: center; font-size: 10px; }
  .badge { display: inline-block; padding: 3px 12px; border-radius: 10px; font-weight: bold; }
  .badge-paid { background: #d1fae5; color: #065f46; }
  .badge-due { background: #fef3c7; color: #92400e; }
  .signs { margin-top: 44px; }
  .signs td { width: 50%; text-align: center; padding: 0 16px; }
  .signs .line { border-top: 1px solid #6b7280; padding-top: 4px; font-size: 9.5px; }
  .footer { margin-top: 16px; text-align: center; font-size: 8.5px; color: #9ca3af; }
</style>
</head>
<body>
@php $taka = fn ($v) => 'Tk ' . number_format((float) $v, 2); @endphp
<div class="header">
  <div class="name">{{ $institution }}</div>
  <div class="title">MONEY RECEIPT</div>
</div>

<table class="info">
  <tr><td class="lbl">Receipt no.</td><td class="val">{{ $payment->transaction_id }}</td></tr>
  <tr><td class="lbl">Date</td><td class="val">{{ $payment->payment_date?->format('d M Y') }}</td></tr>
  <tr><td class="lbl">Student</td><td class="val">{{ $student?->user?->name }} ({{ $student?->student_id }})</td></tr>
  @if ($group)
    <tr><td class="lbl">{{ $group->kind === 'batch' ? 'Batch' : 'Class' }}</td><td class="val">{{ $group->display_name }}@if ($roll) · Roll {{ $roll }}@endif</td></tr>
  @endif
  <tr><td class="lbl">Invoice</td><td class="val">{{ $invoice?->invoice_number }}</td></tr>
</table>

<table class="lines">
  <thead><tr><th>Description</th><th class="r">Amount</th></tr></thead>
  <tbody>
    <tr><td>{{ $invoice?->description }}</td><td class="r">{{ $taka($invoice?->amount) }}</td></tr>
    @if ((float) $invoice?->discount > 0)
      <tr><td>Discount{{ $invoice->remarks ? ' (' . $invoice->remarks . ')' : '' }}</td><td class="r">− {{ $taka($invoice->discount) }}</td></tr>
    @endif
    @if ((float) $invoice?->fine > 0)
      <tr><td>Fine</td><td class="r">{{ $taka($invoice->fine) }}</td></tr>
    @endif
    <tr><td>Payable</td><td class="r">{{ $taka($invoice?->net_amount) }}</td></tr>
    <tr class="paid"><td>Received ({{ ucwords(str_replace('_', ' ', $payment->payment_method)) }})</td><td class="r">{{ $taka($payment->amount) }}</td></tr>
  </tbody>
</table>

<div class="status">
  @if ($invoice && $invoice->due_amount > 0.004)
    <span class="badge badge-due">Still due: {{ $taka($invoice->due_amount) }}</span>
  @else
    <span class="badge badge-paid">PAID IN FULL</span>
  @endif
</div>

<table class="signs">
  <tr>
    <td><div class="line">Received by{{ $payment->collectedBy ? ': ' . $payment->collectedBy->name : '' }}</div></td>
    <td><div class="line">Guardian</div></td>
  </tr>
</table>

<div class="footer">{{ $institution }} · Printed {{ now()->format('d M Y') }}</div>
</body>
</html>
