<?php

namespace App\Exports;

use App\Student;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsListExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable;

    /**
     * Constructor Method
     * @param string, string
     */
    public function __construct(string $class, $group = null)
    {
        $this->class = $class;
        $this->group = $group;
    }

    /**
     * Returns array of headings for excel document
     * @return array 
     */
    public function headings(): array
    {
        return [
            'Roll',
            'Name',
        ];
    }

    /**
     * Customize the student object for specific data for excel document
     * @param App\Student
     * @return array 
     */
    public function map($student): array
    {
        return [
            $student->roll_no,
            $student->name,
        ];
    }

    /**
     * Returns Eloquent Object for further processing
     * @var group : if not null Data of student belongs to lab
     * @var class : Data of student belongs to class
     * @return Illuminate\Database\Eloquent\Builder 
     */
    public function query()
    {
        if ($this->group == null) {
            return Student::query()->where('class', $this->class);
        } else {
            return Student::query()->where('class', $this->class)->where('group', $this->group);
        }
    }
}
