<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDetail extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'pan_number',
        'aadhaar_number',
        'date_of_birth',
        'address',
        'city',
        'state',
        'pincode',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'bank_verification_status',
        'pan_document',
        'aadhaar_front',
        'aadhaar_back',
        'selfie_photo',
        'face_verification_status',
        'face_verification_notes',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
