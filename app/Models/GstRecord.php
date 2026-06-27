<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GstRecord extends Model
{
    protected $fillable = [
        'report_date',
        'total_taxable_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_gst',
        'transaction_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'total_taxable_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'total_gst' => 'decimal:2',
        ];
    }
}
