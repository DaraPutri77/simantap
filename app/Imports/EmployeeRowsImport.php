<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeeRowsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $collection
     */
    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }
}
