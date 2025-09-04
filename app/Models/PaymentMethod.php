<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'min_amount',
        'max_amount',
        'meta',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'json',
        ];
    }

    public function gateways(): BelongsToMany
    {
        return $this->belongsToMany(PaymentGateway::class, 'payment_gateway_methods')
            ->withPivot(['min_amount', 'max_amount', 'min_withdraw_amount', 'max_withdraw_amount']);
    }
}
