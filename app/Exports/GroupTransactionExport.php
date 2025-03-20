<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class GroupTransactionExport implements FromCollection, WithHeadings
{
    protected $transactions;
    protected $transactionType;

    public function __construct($transactions, $transactionType)
    {
        $this->transactions = $transactions;
        $this->transactionType = $transactionType;
    }

    public function collection()
    {
        return collect($this->transactions);
    }

    public function headings(): array
    {
        $headings = [
            trans('public.date'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id_number'),
            trans('public.role'),
            trans('public.account'),
            trans('public.account_type'),
            trans('public.amount') . ' ($)',
        ];

        if ($this->transactionType === 'withdrawal') {
            $headings[] = trans('public.approve_date');
        }

        return $headings;
    }
}

