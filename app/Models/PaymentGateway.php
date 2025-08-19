<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateway extends Model
{
    use SoftDeletes;

    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'payment_gateway_methods')
            ->withPivot(['min_amount', 'max_amount']);
    }
}
