<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'report_date',
        'new_users',
        'active_investors',
        'gold_holdings_total',
        'silver_holdings_total',
        'revenue_total',
        'transaction_count',
        'gst_collected',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'gold_holdings_total' => 'decimal:4',
            'silver_holdings_total' => 'decimal:4',
            'revenue_total' => 'decimal:2',
            'gst_collected' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
