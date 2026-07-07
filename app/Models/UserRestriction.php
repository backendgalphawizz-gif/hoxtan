<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRestriction extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_blocked',
        'bonus_blocked',
        'referral_blocked',
        'withdrawal_hold',
        'support_notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'wallet_blocked' => 'boolean',
            'bonus_blocked' => 'boolean',
            'referral_blocked' => 'boolean',
            'withdrawal_hold' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
