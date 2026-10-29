<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 28px 32px; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; }
  .page-break { page-break-after: always; }
  .header { text-align: center; border-bottom: 3px solid #047857; padding-bottom: 10px; margin-bottom: 12px; }
  .header .name { font-size: 20px; font-weight: bold; color: #065f46; }
  .header .title { display: inline-block; margin-top: 6px; background: #047857; color: #fff; font-size: 11px; font-weight: bold; padding: 3px 14px; border-radius: 10px; letter-spacing: 1px; }
  .header .exam { font-size: 11px; margin-top: 6px; color: #374151; }
  .draft { text-align: center; color: #b45309; background: #fffbeb; border: 1px solid #fcd34d; padding: 4px; font-size: 9.5px; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  .info td { padding: 4px 6px; font-size: 10.5px; }
  .info .lbl { color: #6b7280; width: 16%; }
  .info .val { font-weight: bold; width: 34%; }
  .marks { margin-top: 12px; }
  .marks th { background: #047857; color: #fff; padding: 6px; font-size: 10px; text-align: center; }
  .marks th.left, .marks td.left { text-align: left; }
  .marks td { padding: 5px 6px; border: 1px solid #d1d5db; text-align: center; }
  .marks tr:nth-child(even) td { background: #f0fdf4; }
  .fail { color: #b91c1c; font-weight: bold; }
  .muted { color: #9ca3af; }
  .tag { font-size: 8.5px; color: #047857; }
  .summary { margin-top: 12px; }
  .summary td { border: 1px solid #d1d5db; padding: 6px; text-align: center; width: 16.6%; }
  .summary .lbl { font-size: 8.5px; color: #6b7280; text-transform: uppercase; }
  .summary .val { font-size: 14px; font-weight: bold; color: #065f46; margin-top: 2px; }
  .summary .val.fail { color: #b91c1c; }
  .remarks { margin-top: 12px; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px; min-height: 50px; }
  .remarks .lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; margin-bottom: 3px; }
  .scale { margin-top: 10px; font-size: 8.5px; color: #6b7280; }
  .scale td { border: 1px solid #e5e7eb; padding: 2px 4px; text-align: center; }
  .signs { margin-top: 48px; }
  .signs td { width: 33%; text-align: center; padding: 0 12px; }
  .signs .line { border-top: 1px solid #6b7280; padding-top: 4px; font-size: 9.5px; color: #374151; }
  .footer { margin-top: 18px; text-align: center; font-size: 8.5px; color: #9ca3af; }
</style>
</head>
<body>
@php
  $title = match ($mode) { 'marks' => 'RESULT CARD', 'remarks' => 'PROGRESS REPORT', default => 'REPORT CARD' };
  $statusText = ['passed' => 'Passed', 'failed' => 'Failed', 'incomplete' => 'Incomplete'];
  $isBatch = $group->kind === 'batch';
@endphp
@foreach ($students as $s)
<div class="{{ $loop->last ? '' : 'page-break' }}">
  <div class="header">
    <div class="name">{{ $institution }}</div>
    <div class="title">{{ $title }}</div>
    <div class="exam">{{ $exam->name }} · Academic year {{ $exam->academic_year }}@if ($exam->starts_on) · {{ $exam->starts_on->format('d M Y') }}@if ($exam->ends_on && ! $exam->ends_on->eq($exam->starts_on)) – {{ $exam->ends_on->format('d M Y') }}@endif @endif</div>
  </div>

  @unless ($exam->isPublished())
    <div class="draft">DRAFT — results are not published yet and may still change.</div>
  @endunless

  <table class="info">
    <tr>
      <td class="lbl">Name</td><td class="val">{{ $s['name'] }}</td>
      <td class="lbl">Student ID</td><td class="val">{{ $s['student_id'] ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">{{ $isBatch ? 'Batch' : 'Class' }}</td><td class="val">{{ $group->display_name }}</td>
      <td class="lbl">Roll</td><td class="val">{{ $s['roll_number'] ?? '—' }}</td>
    </tr>
  </table>

  <table class="marks">
    <thead>
      <tr>
        <th style="width: 6%">#</th>
        <th class="left">Subject</th>
        <th style="width: 12%">Full marks</th>
        @if ($mode === 'marks')<th style="width: 12%">Pass marks</th>@endif
        <th style="width: 14%">Obtained</th>
        @if ($mode === 'gpa5')
          <th style="width: 12%">Grade</th>
          <th style="width: 12%">Point</th>
        @elseif ($mode === 'marks')
          <th style="width: 12%">Result</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @foreach ($s['subjects'] as $i => $sub)
        @php $failed = $sub['letter'] === 'F'; @endphp
        <tr>
          <td>{{ $i + 1 }}</td>
          <td class="left">{{ $sub['name'] }} @if ($sub['is_fourth'])<span class="tag">(4th subject)</span>@endif</td>
          <td>{{ $sub['full_marks'] }}</td>
          @if ($mode === 'marks')<td>{{ $sub['pass_marks'] }}</td>@endif
          <td class="{{ $failed ? 'fail' : '' }}">
            @if ($sub['is_absent']) Absent
            @elseif ($sub['marks'] === null) <span class="muted">—</span>
            @else {{ rtrim(rtrim(number_format($sub['marks'], 2), '0'), '.') }}
            @endif
          </td>
          @if ($mode === 'gpa5')
            <td class="{{ $failed ? 'fail' : '' }}">{{ $sub['letter'] ?? '—' }}</td>
            <td class="{{ $failed ? 'fail' : '' }}">{{ $sub['point'] !== null ? number_format($sub['point'], 2) : '—' }}</td>
          @elseif ($mode === 'marks')
            <td class="{{ $failed ? 'fail' : '' }}">{{ $sub['letter'] === null ? '—' : ($failed ? 'Fail' : 'Pass') }}</td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="summary">
    <tr>
      <td><div class="lbl">Total</div><div class="val">{{ rtrim(rtrim(number_format($s['total'], 2), '0'), '.') }}/{{ $s['full_total'] }}</div></td>
      <td><div class="lbl">Percentage</div><div class="val">{{ $s['percent'] !== null ? number_format($s['percent'], 2) . '%' : '—' }}</div></td>
      @if ($mode === 'gpa5')
        <td><div class="lbl">GPA</div><div class="val {{ $s['status'] === 'failed' ? 'fail' : '' }}">{{ $s['gpa'] !== null ? number_format($s['gpa'], 2) : '—' }}</div></td>
        <td><div class="lbl">Letter grade</div><div class="val {{ $s['status'] === 'failed' ? 'fail' : '' }}">{{ $s['letter'] ?? '—' }}</div></td>
      @endif
      <td><div class="lbl">Result</div><div class="val {{ $s['status'] === 'failed' ? 'fail' : '' }}">{{ $statusText[$s['status']] }}</div></td>
      @if ($mode !== 'remarks')
        <td><div class="lbl">Position in {{ $isBatch ? 'batch' : 'section' }}</div><div class="val">{{ $s['section_position'] ?? '—' }}<span style="font-size: 9px; color: #6b7280"> / {{ $sectionSize }}</span></div></td>
      @endif
      @if ($mode === 'gpa5')
        <td><div class="lbl">Position in class</div><div class="val">{{ $s['class_position'] ?? '—' }}</div></td>
      @endif
    </tr>
  </table>

  <div class="remarks">
    <div class="lbl">{{ $isBatch ? "Teacher's remarks" : "Class teacher's remarks" }}</div>
    {{ $s['remarks'] ?? '' }}
  </div>

  @if ($mode === 'gpa5')
    <table class="scale">
      <tr>
        <td>80–100 A+ (5.00)</td><td>70–79 A (4.00)</td><td>60–69 A- (3.50)</td><td>50–59 B (3.00)</td>
        <td>40–49 C (2.00)</td><td>33–39 D (1.00)</td><td>0–32 F (0.00)</td>
      </tr>
    </table>
  @endif

  <table class="signs">
    <tr>
      <td><div class="line">{{ $isBatch ? 'Teacher' : 'Class Teacher' }}</div></td>
      <td><div class="line">Guardian</div></td>
      <td><div class="line">{{ $isBatch ? 'Director' : 'Head Teacher' }}</div></td>
    </tr>
  </table>

  <div class="footer">Generated on {{ $generated_at }} · {{ $institution }}</div>
</div>
@endforeach
</body>
</html>
