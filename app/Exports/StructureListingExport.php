<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StructureListingExport implements FromCollection, WithHeadings
{
    protected $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    public function collection(): Collection
    {
        $data = array();
        foreach ($this->collection as $user) {
            // Prepare the formatted data for export
            $data[] = [
                'joined_date' => $user->created_at ? $user->created_at->format('Y-m-d') : '',
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'level' => 'Level ' . $user->level,
                'role' => strtoupper($user->role) ?? '',
                'upline' => $user->upline ? $user->upline->name : ''
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Joined Date',
            'Name',
            'Email',
            'Level',
            'Role',
            'Upline'
        ];
    }
}
