<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradingPlatform extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'platform_name',
        'slug',
        'server',
        'status',
    ];

    public function account_types(): HasMany
    {
        return $this->hasMany(AccountType::class, 'trading_platform_id');
    }
}
