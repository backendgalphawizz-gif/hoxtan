<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentGoal extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'metal_type',
        'target_grams',
        'current_grams',
        'target_amount',
        'target_date',
        'status',
        'admin_created',
        'target_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'target_grams' => 'decimal:4',
            'current_grams' => 'decimal:4',
            'target_amount' => 'decimal:2',
            'target_date' => 'date',
            'admin_created' => 'boolean',
            'target_user_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercentage(): float
    {
        if ($this->target_grams <= 0) {
            return 0;
        }

        return min(100, ($this->current_grams / $this->target_grams) * 100);
    }
}
