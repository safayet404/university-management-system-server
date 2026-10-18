<?php

namespace App\Exports;

use App\Models\FacultyProfile;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacultyExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(protected array $filters = []) {}

    public function query()
    {
        $query = FacultyProfile::with(['user', 'department'])->latest();
        if (!empty($this->filters['department_id'])) $query->where('department_id', $this->filters['department_id']);
        if (!empty($this->filters['designation']))   $query->where('designation', $this->filters['designation']);
        return $query;
    }

    public function headings(): array
    {
        return ['Employee ID', 'Name', 'Email', 'Phone', 'Gender', 'Department', 'Designation', 'Employment Type', 'Specialization', 'Highest Degree', 'Publications', 'Joining Date', 'Status'];
    }

    public function map($f): array
    {
        return [
            $f->employee_id,
            $f->user?->name,
            $f->user?->email,
            $f->user?->phone,
            $f->user?->gender,
            $f->department?->name,
            $f->designation,
            $f->employment_type,
            $f->specialization,
            $f->highest_degree,
            $f->publications_count,
            $f->joining_date?->format('Y-m-d'),
            $f->employment_status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']]]];
    }
}
