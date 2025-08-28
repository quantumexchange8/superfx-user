<?php

namespace App\Services;

use App\Models\RunningNumber;
use App\Models\UsedOrderNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class RunningNumberService
{
    public static function getID($type): string
    {
        do {
            $format = RunningNumber::where('type', $type)->lockForUpdate()->first();
            $lastID = $format->last_number + 1;

            $orderNumber = $format->prefix . Str::padLeft($lastID, $format->digits, "0");

            try {
                // Try reserving the number
                UsedOrderNumber::create([
                    'type'         => $type,
                    'order_number' => $orderNumber,
                ]);

                // Increment last_number only after successful reserve
                $format->increment('last_number');

                return $orderNumber;
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    // Duplicate, try again
                    continue;
                }
                throw $e;
            }

        } while (true);
    }
}
