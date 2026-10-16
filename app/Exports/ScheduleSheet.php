<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ScheduleSheet implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Data', 'Dia', 'Turno', 'Início', 'Duração', 'Membro', 'Matrícula'];
    }
}
