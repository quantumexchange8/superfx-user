<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTypeAccess extends Model
{
    // use SoftDeletes;
    
    protected $fillable = [
        'account_type_id',
        'user_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
    }

}
