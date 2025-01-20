<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Carbon\Carbon;

class RebateHistoryExport implements FromCollection, WithHeadings
{
    protected $histories;

    public function __construct($histories)
    {
        $this->histories = $histories;
    }

    public function collection()
    {
        return $this->histories->get()->map(function ($history) {
            return [
                'created_at' => Carbon::parse($history->created_at)->format('Y-m-d'),
                'deal_id' => $history->deal_id,
                'open_time' => $history->open_time,
                'closed_time' => $history->closed_time,
                'trade_open_price' => (string) ($history->trade_open_price ?? '0'),
                'trade_close_price' => (string) ($history->trade_close_price ?? '0'),
                't_type' => trans("public.{$history->t_type}"),
                'downline_name' => $history->downline->name ?? '-',
                'downline_email' => $history->downline->email ?? '-',
                'downline_id_number' => $history->downline->id_number ?? '-',
                'trade_profit' => (string) ($history->trade_profit ?? '0'),
                'meta_login' => $history->meta_login,
                'symbol' => $history->symbol,
                'volume' => (string) ($history->volume ?? '0'),
                'rebate' => (string) ($history->revenue ?? '0')
            ];
        });
        return $histories;
    }

    public function headings(): array
{
    
    return [
        trans('public.date'),
        trans('public.ticket'),
        trans('public.open_time'),
        trans('public.closed_time'),
        trans('public.open_price') . ' ($)',
        trans('public.close_price') . ' ($)',
        trans('public.type'),
        trans('public.name'),
        trans('public.email'),
        trans('public.id_number'),
        trans('public.profit') . ' ($)',
        trans('public.account'),
        trans('public.product'),
        trans('public.volume') . ' (Ł)',
        trans('public.rebate') . ' ($)',
    ];
}
}
