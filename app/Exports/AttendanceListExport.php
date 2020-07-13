<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AttendanceListExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    use Exportable;

    /**
     * Constructor Method
     * @param string, string
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Returns array of headings for excel document
     * @return array 
     */
    public function headings(): array
    {
        return [
            'Roll No',
            'Name',
            'Status',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->data);
    }
}
