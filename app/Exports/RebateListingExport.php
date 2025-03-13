<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Carbon\Carbon;

class RebateListingExport implements FromCollection, WithHeadings
{
    protected $listings;

    public function __construct($listings)
    {
        $this->listings = $listings;
    }

    public function collection()
    {
        $result = $this->listings->map(function ($listing) {
            return [
                'name' => $listing['name'] ?? '-',
                'id' => $listing['id_number'] ?? '-',
                'account' => $listing['meta_login'] ?? '-',
                'volume' => number_format((float)($listing['volume'] ?? 0), 2),
                'rebate' => number_format((float)($listing['rebate'] ?? 0), 2), 
            ];
        });

        return collect($result);
    }

    public function headings(): array
    {
        return [
            trans('public.name'),
            trans('public.id'),
            trans('public.account'),
            trans('public.volume'),
            trans('public.rebate'),
        ];
    }
}
