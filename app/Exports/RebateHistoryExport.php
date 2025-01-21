<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Carbon\Carbon;

class RebateHistoryExport implements FromCollection, WithHeadings
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        $histories = $this->query
                        ->orderByDesc('created_at')
                        ->get();

        $result = array();
        foreach ($histories as $history){
            $result[] = array(
                'created_at' => Carbon::parse($history->created_at)->format('Y-m-d'),
                'deal_id' => $history->deal_id,
                'open_time' => $history->open_time,
                'closed_time' => $history->closed_time,
                'trade_open_price' => (float)$history->trade_open_price,
                'trade_close_price' => (float)$history->trade_close_price,
                't_type' => trans("public.{$history->t_type}"),
                'downline_name' => $history->downline->name ?? '-',
                'downline_email' => $history->downline->email ?? '-',
                'downline_id_number' => $history->downline->id_number ?? '-',
                'trade_profit' => number_format((float)$history->trade_profit, 2, '.', ''),
                'meta_login' => $history->meta_login,
                'symbol' => $history->symbol,
                'volume' => (float)$history->volume,
                'rebate' => number_format((float)$history->revenue, 2, '.', ''),
                'status' => trans('public.completed')
            );
        }

        return collect($result);
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
            trans('public.status'),
        ];
    }
}
