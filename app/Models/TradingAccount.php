<?php

namespace App\Models;

use App\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAccount extends Model
{
    use SoftDeletes, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        $account = $this->fresh();

        return LogOptions::defaults()
            ->useLogName('trading_account')
            ->logOnly([
                'id',
                'user_id',
                'meta_login',
                'account_type_id',
                'balance',
                'credit',
                'margin_leverage',
                'acc_status',
            ])
            ->setDescriptionForEvent(function (string $eventName) use ($account) {
                $actorName = Auth::user() ? Auth::user()->name : 'System';

                return "$actorName has {$eventName} trading account with ID: {$account->id}";
            })
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relations
    public function account_type(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function asset_subscriptions(): HasMany
    {
        return $this->hasMany(AssetSubscription::class, 'meta_login', 'meta_login');
    }

    public function trading_user(): BelongsTo
    {
        return $this->belongsTo(TradingUser::class, 'meta_login', 'meta_login');
    }
}
