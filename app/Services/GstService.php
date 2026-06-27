<?php

namespace App\Services;

use App\Models\GstRecord;
use App\Models\Investment;
use Carbon\Carbon;

class GstService
{
    public const GST_RATE = 0.03; // 3% GST (1.5% CGST + 1.5% SGST)

    public function calculateForDate(Carbon $date): GstRecord
    {
        $investments = Investment::query()
            ->where('status', 'completed')
            ->whereDate('created_at', $date)
            ->get();

        $totalTaxable = $investments->sum('amount');
        $totalGst = $investments->sum('gst_amount');

        $cgst = round($totalGst / 2, 2);
        $sgst = round($totalGst / 2, 2);

        return GstRecord::updateOrCreate(
            ['report_date' => $date->toDateString()],
            [
                'total_taxable_amount' => $totalTaxable,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => 0,
                'total_gst' => $totalGst,
                'transaction_count' => $investments->count(),
            ]
        );
    }

    public function calculateGstAmount(float $amount): array
    {
        $gstAmount = round($amount * self::GST_RATE, 2);
        $cgst = round($gstAmount / 2, 2);
        $sgst = round($gstAmount / 2, 2);

        return [
            'gst_amount' => $gstAmount,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'total' => round($amount + $gstAmount, 2),
        ];
    }
}
