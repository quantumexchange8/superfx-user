<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Carbon\Carbon;


class TransactionDetailExport implements FromCollection, WithHeadings
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        $transactions = $this->query
                        ->orderByDesc('created_at')
                        ->get();

        $result = array();
        foreach ($transactions as $transaction){
            $result[] = array(
                'created_at' => $transaction->created_at,
                'transaction_id' => $transaction->transaction_number,
                'description' => $transaction->transaction_type ? trans("public.{$transaction->transaction_type}") : "",
                'account' => $transaction->from_meta_login ?? $transaction->to_meta_login,
                'amount' => (float)$transaction->amount,
                'fee' => (float)$transaction->transaction_charges,
                'final_amount' => (float)$transaction->transaction_amount,
                'status' => trans("public.{$transaction->status}"),
                'receiving_address' => $transaction->to_wallet_address,
                'platform' => $transaction->payment_platform ? trans("public.{$transaction->payment_platform}") : "",
                'bank_name' => $transaction->payment_platform_name,
                'bank_code' => $transaction->bank_code,
                'payment_account_type' => $transaction->payment_account_type ? trans("public.{$transaction->payment_account_type}") : "",
                'account_no' => $transaction->payment_account_no,
            );
        }

        return collect($result);
    }

    public function headings(): array
    {
        
        return [
            trans('public.date'),
            trans('public.transaction_id'),
            trans('public.description'),
            trans('public.account'),
            trans('public.amount') . ' ($)',
            trans('public.fee') . ' ($)',
            trans('public.final_amount') . ' ($)',
            trans('public.status'),
            trans('public.receiving_address'),
            trans('public.platform'),
            trans('public.platform_name'),
            trans('public.bank_code'),
            trans('public.payment_account_type'),
            trans('public.account_no'),
        ];
    }

}
