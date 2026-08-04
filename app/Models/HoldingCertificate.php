<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoldingCertificate extends Model
{
    protected $fillable = [
        'certificate_number',
        'user_id',
        'investment_id',
        'account_holder_name',
        'metal_type',
        'holding_grams',
        'purity',
        'file_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'holding_grams' => 'decimal:4',
            'issued_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
