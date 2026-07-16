<?php

namespace App\Support;

use App\Models\Investment;
use App\Models\JewelleryOrder;
use App\Models\MetalWithdrawal;
use App\Models\OldGoldBooking;
use App\Models\Redemption;
use App\Models\SigInstallment;
use App\Models\WalletTransaction;
use App\Services\HoldingCertificateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AccountTransactionPayload
{
    public static function fromInvestment(Investment $investment): array
    {
        $metal = ucfirst((string) $investment->metal_type);
        $type = $investment->type === 'sell' ? 'sell' : 'buy';
        $investment->loadMissing(['holdingCertificate', 'invoice']);

        $meta = [
            'investment_id' => $investment->id,
            'rate_per_gram' => $investment->rate_per_gram !== null ? (float) $investment->rate_per_gram : null,
            'gst_amount' => $investment->gst_amount !== null ? (float) $investment->gst_amount : null,
        ];

        $certificate = null;
        if ($type === 'buy' && $investment->holdingCertificate) {
            $certificate = app(HoldingCertificateService::class)
                ->payload($investment->holdingCertificate);
            $meta['certificate_number'] = $investment->holdingCertificate->certificate_number;
        }

        $invoice = $investment->invoice;
        if ($invoice) {
            $meta['invoice_number'] = $invoice->invoice_number;
            $meta['invoice_download_url'] = route('api.invoices.download', $invoice);
        }

        $payload = self::base(
            id: 'investment:'.$investment->id,
            sourceType: 'investment',
            category: $type,
            referenceId: $investment->reference_id,
            title: $metal.' '.ucfirst($type),
            subtitle: 'Digital '.$investment->metal_type.' '.$type,
            amount: (float) ($investment->total_amount ?: $investment->amount),
            direction: $type === 'sell' ? 'credit' : 'debit',
            status: $investment->status,
            statusLabel: config('account_activity.investment_statuses.'.$investment->status, Str::headline((string) $investment->status)),
            occurredAt: $investment->created_at,
            metalType: $investment->metal_type,
            quantityGrams: $investment->quantity_grams !== null ? (float) $investment->quantity_grams : null,
            meta: $meta,
        );

        $payload['certificate'] = $certificate;
        $payload['invoice'] = $invoice
            ? app(\App\Services\InvoiceService::class)->apiPayload($invoice)
            : null;

        return $payload;
    }

    public static function fromWallet(WalletTransaction $transaction): array
    {
        $sourceLabel = config('account_activity.wallet_sources.'.$transaction->source, Str::headline((string) $transaction->source));

        return self::base(
            id: 'wallet:'.$transaction->id,
            sourceType: 'wallet',
            category: 'wallet',
            referenceId: $transaction->reference_id,
            title: $transaction->type === 'credit' ? 'Wallet Credit' : 'Wallet Debit',
            subtitle: $transaction->description ?: $sourceLabel,
            amount: (float) $transaction->amount,
            direction: $transaction->type === 'credit' ? 'credit' : 'debit',
            status: 'completed',
            statusLabel: 'Completed',
            occurredAt: $transaction->created_at,
            metalType: null,
            quantityGrams: null,
            meta: [
                'wallet_transaction_id' => $transaction->id,
                'source' => $transaction->source,
                'balance_after' => $transaction->balance_after !== null ? (float) $transaction->balance_after : null,
            ],
        );
    }

    public static function fromSigInstallment(SigInstallment $installment): array
    {
        $installment->loadMissing('plan');
        $plan = $installment->plan;

        return self::base(
            id: 'sig:'.$installment->id,
            sourceType: 'sig',
            category: 'sig',
            referenceId: $installment->reference_id,
            title: $plan ? SigPayload::transactionTitle($plan) : 'SIG Transaction',
            subtitle: 'Systematic investment',
            amount: (float) $installment->amount,
            direction: 'debit',
            status: $installment->status,
            statusLabel: SigPayload::installmentStatusLabel($installment->status),
            occurredAt: $installment->processed_at ?? $installment->scheduled_at ?? $installment->created_at,
            metalType: $plan?->metal_type,
            quantityGrams: $installment->quantity_grams !== null ? (float) $installment->quantity_grams : null,
            meta: [
                'sig_installment_id' => $installment->id,
                'sig_plan_id' => $installment->sig_plan_id,
                'rate_per_gram' => $installment->rate_per_gram !== null ? (float) $installment->rate_per_gram : null,
            ],
        );
    }

    public static function fromJewelleryOrder(JewelleryOrder $order): array
    {
        $order->loadMissing(['items.product', 'invoice']);
        $firstItem = $order->items->first();
        $invoice = $order->invoice;

        $meta = [
            'order_id' => $order->id,
            'order_number_display' => '#'.$order->order_number,
            'item_count' => (int) $order->items->sum('quantity'),
            'payment_mode' => $order->payment_mode,
        ];

        if ($invoice) {
            $meta['invoice_number'] = $invoice->invoice_number;
            $meta['invoice_download_url'] = route('api.invoices.download', $invoice);
        }

        $payload = self::base(
            id: 'jewellery_order:'.$order->id,
            sourceType: 'jewellery_order',
            category: 'jewellery',
            referenceId: $order->order_number,
            title: $firstItem?->product?->name ?? 'Jewellery Order',
            subtitle: 'Jewellery purchase',
            amount: (float) $order->total_amount,
            direction: 'debit',
            status: $order->status,
            statusLabel: OrderPayload::statusLabel($order->status),
            occurredAt: $order->created_at,
            metalType: $firstItem?->product?->metal_type,
            quantityGrams: null,
            meta: $meta,
        );

        $payload['invoice'] = $invoice
            ? app(\App\Services\InvoiceService::class)->apiPayload($invoice)
            : null;

        return $payload;
    }

    public static function fromOldGoldBooking(OldGoldBooking $booking): array
    {
        $payload = self::base(
            id: 'old_gold:'.$booking->id,
            sourceType: 'old_gold',
            category: 'sell',
            referenceId: $booking->booking_number,
            title: $booking->item_name ?? 'Sell Old Jewellery',
            subtitle: 'Old gold sell request',
            amount: (float) ($booking->quoted_amount ?? 0),
            direction: 'credit',
            status: SellJewelleryPayload::normalizeStatus($booking->status),
            statusLabel: SellJewelleryPayload::statusLabel($booking->status),
            occurredAt: $booking->created_at,
            metalType: $booking->metal_type,
            quantityGrams: $booking->estimated_weight_grams !== null ? (float) $booking->estimated_weight_grams : null,
            meta: [
                'booking_id' => $booking->id,
                'booking_number_display' => '#'.$booking->booking_number,
            ],
        );

        $payload['invoice'] = null;

        return $payload;
    }

    public static function fromHoldingsSell(MetalWithdrawal $withdrawal): array
    {
        $metal = ucfirst((string) $withdrawal->metal_type);
        $statusLabels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];

        return self::base(
            id: 'holdings_sell:'.$withdrawal->id,
            sourceType: 'holdings_sell',
            category: 'sell',
            referenceId: $withdrawal->reference_id,
            title: $metal.' Holdings Sell',
            subtitle: 'Digital '.$withdrawal->metal_type.' holdings sell',
            amount: (float) $withdrawal->amount,
            direction: 'credit',
            status: (string) $withdrawal->status,
            statusLabel: $statusLabels[$withdrawal->status] ?? Str::headline((string) $withdrawal->status),
            occurredAt: $withdrawal->requested_at ?? $withdrawal->created_at,
            metalType: $withdrawal->metal_type,
            quantityGrams: $withdrawal->quantity_grams !== null ? (float) $withdrawal->quantity_grams : null,
            meta: [
                'withdrawal_id' => $withdrawal->id,
                'source_lot_id' => $withdrawal->source_lot_id,
                'from_holdings' => true,
                'rate_per_gram' => $withdrawal->rate_per_gram !== null ? (float) $withdrawal->rate_per_gram : null,
                'investment_id' => $withdrawal->investment_id,
                'auto_approve_at' => $withdrawal->auto_approve_at?->toIso8601String(),
                'payout_reference' => $withdrawal->payout_reference,
            ],
        );
    }

    public static function fromRedemption(Redemption $redemption): array
    {
        return self::base(
            id: 'redemption:'.$redemption->id,
            sourceType: 'redemption',
            category: 'redemption',
            referenceId: $redemption->reference_id,
            title: ucfirst((string) $redemption->metal_type).' Redemption',
            subtitle: 'Physical redemption',
            amount: (float) $redemption->amount,
            direction: 'debit',
            status: $redemption->status,
            statusLabel: Str::headline((string) $redemption->status),
            occurredAt: $redemption->created_at,
            metalType: $redemption->metal_type,
            quantityGrams: $redemption->quantity_grams !== null ? (float) $redemption->quantity_grams : null,
            meta: [
                'redemption_id' => $redemption->id,
                'tracking_number' => $redemption->tracking_number,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function base(
        string $id,
        string $sourceType,
        string $category,
        ?string $referenceId,
        string $title,
        string $subtitle,
        float $amount,
        string $direction,
        string $status,
        string $statusLabel,
        ?Carbon $occurredAt,
        ?string $metalType,
        ?float $quantityGrams,
        array $meta = [],
    ): array {
        return [
            'id' => $id,
            'source_type' => $sourceType,
            'category' => $category,
            'reference_id' => $referenceId,
            'title' => $title,
            'subtitle' => $subtitle,
            'amount' => $amount,
            'amount_display' => ($direction === 'credit' ? '+' : '-').'₹'.number_format($amount, 2),
            'direction' => $direction,
            'status' => $status,
            'status_label' => $statusLabel,
            'metal_type' => $metalType,
            'quantity_grams' => $quantityGrams,
            'quantity_display' => $quantityGrams !== null
                ? rtrim(rtrim(number_format($quantityGrams, 4, '.', ''), '0'), '.').' g'
                : null,
            'occurred_at' => $occurredAt?->toIso8601String(),
            'occurred_date' => $occurredAt?->format('d M Y'),
            'occurred_at_display' => $occurredAt
                ? $occurredAt->format('H:i').' • '.$occurredAt->format('d F Y')
                : null,
            'meta' => $meta,
            'invoice' => null,
        ];
    }
}
