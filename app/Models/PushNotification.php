<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotification extends Model
{
    protected $fillable = [
        'title',
        'body',
        'target',
        'target_user_ids',
        'status',
        'scheduled_at',
        'sent_at',
        'recipients_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_user_ids' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
