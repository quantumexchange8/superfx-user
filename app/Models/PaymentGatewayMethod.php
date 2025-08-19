<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGatewayMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_gateway_id',
        'payment_method_id',
    ];

    public function payment_gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function payment_method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
