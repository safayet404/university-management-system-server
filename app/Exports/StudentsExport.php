<?php

namespace App\Exports;

use App\Models\StudentProfile;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $query = StudentProfile::with(['user', 'department', 'program'])->latest();
        if (!empty($this->filters['department_id'])) $query->where('department_id', $this->filters['department_id']);
        if (!empty($this->filters['academic_status'])) $query->where('academic_status', $this->filters['academic_status']);
        return $query;
    }

    public function headings(): array
    {
        return ['Student ID', 'Name', 'Email', 'Phone', 'Gender', 'Department', 'Program', 'Batch', 'Semester', 'Section', 'CGPA', 'Academic Status', 'Admission Date'];
    }

    public function map($s): array
    {
        return [
            $s->student_id, $s->user?->name, $s->user?->email, $s->user?->phone,
            $s->user?->gender, $s->department?->name, $s->program?->name,
            $s->batch, $s->semester, $s->section, $s->cgpa,
            $s->academic_status, $s->admission_date?->format('Y-m-d'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']]]];
    }
}
