<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CloseTradesExport implements FromCollection, WithHeadings
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function headings(): array
    {
        return [
            trans('public.date'),
            trans('public.product'),
            trans('public.ticket'),
            trans('public.open_time'),
            trans('public.closed_time'),
            trans('public.open_price') . ' ($)',
            trans('public.close_price') . ' ($)',
            trans('public.type'),
            trans('public.name'),
            trans('public.email'),
            trans('public.id'),
            trans('public.account'),
            trans('public.group'),
            trans('public.account_currency'),
            trans('public.lots'),
            trans('public.stop_loss'),
            trans('public.take_profit'),
            trans('public.commission'),
            trans('public.swap'),
            trans('public.profit'),
        ];
    }

    public function collection()
    {
        $data = [];

        foreach ($this->records as $record) {
            $currency = strtolower(optional(optional($record->trading_account)->account_type)->currency);
            $swapKey = "trade_swap_{$currency}";
            $profitKey = "trade_profit_{$currency}";
            
            $data[] = [
                $record->created_at->format('Y/m/d'),
                $record->trade_symbol,
                $record->trade_deal_id,
                $record->trade_open_time,
                $record->trade_close_time,
                number_format((float) (string)$record->trade_open_price, 2, '.', ''),
                number_format((float) (string)$record->trade_close_price, 2, '.', ''),
                $record->trade_type,
                // Check if user exists and has attributes
                isset($record->user) ? $record->user->name : '',
                isset($record->user) ? $record->user->email : '',
                isset($record->user) ? $record->user->id_number : '',
                
                $record->meta_login,
                // Check if trading_account and account_type exist
                isset($record->trading_account) && isset($record->trading_account->account_type) ? $record->trading_account->account_type->name : '',
                isset($record->trading_account) && isset($record->trading_account->account_type) ? $record->trading_account->account_type->currency : '',

                number_format((float) (string)$record->trade_lots, 2, '.', ''),
                number_format((float) (string)$record->trade_sl, 2, '.', ''),
                number_format((float) (string)$record->trade_tp, 2, '.', ''),
                number_format((float) (string)$record->trade_commission, 2, '.', ''),
                number_format((float) (string)($record->$swapKey ?? 0), 2, '.', ''),
                number_format((float) (string)($record->$profitKey ?? 0), 2, '.', ''),
            
            ];
        }

        return collect($data);
    }
}
