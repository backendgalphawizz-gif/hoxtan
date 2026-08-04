<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Invoice;
use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class InvoiceService
{
    public function __construct(
        protected AppSettingService $settings,
    ) {}

    public function generateForInvestment(Investment $investment): ?Invoice
    {
        if ($investment->type !== 'buy' || $investment->status !== 'completed') {
            return null;
        }

        if ($existing = Invoice::query()->where('investment_id', $investment->id)->first()) {
            $this->writeFile($existing);

            return $existing->fresh();
        }

        $investment->loadMissing('user');
        $invoiceNumber = $this->nextInvoiceNumber();

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'user_id' => $investment->user_id,
            'investment_id' => $investment->id,
            'subtotal' => $investment->amount,
            'gst_amount' => $investment->gst_amount,
            'total_amount' => $investment->total_amount,
            'metal_type' => $investment->metal_type,
            'quantity_grams' => $investment->quantity_grams,
            'rate_per_gram' => $investment->rate_per_gram,
            'issued_at' => now(),
        ]);

        $this->writeFile($invoice);

        return $invoice->fresh();
    }

    public function generateForJewelleryOrder(JewelleryOrder $order): ?Invoice
    {
        if (! $this->isJewelleryOrderFullyPaid($order)) {
            return null;
        }

        if ($existing = Invoice::query()->where('jewellery_order_id', $order->id)->first()) {
            $this->writeFile($existing);

            return $existing->fresh();
        }

        $order->loadMissing(['user', 'items.product', 'payment', 'emiInstallments']);
        $invoiceNumber = $this->nextInvoiceNumber();
        $firstItem = $order->items->first();
        $product = $firstItem?->product;

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'user_id' => $order->user_id,
            'jewellery_order_id' => $order->id,
            'subtotal' => $order->subtotal,
            'gst_amount' => $order->gst_amount,
            'total_amount' => $order->isEmi()
                ? (float) ($order->total_emi_cost ?? $order->total_amount)
                : (float) $order->total_amount,
            'metal_type' => $product?->metal_type,
            'quantity_grams' => $product?->weight_grams !== null
                ? round((float) $product->weight_grams * (int) ($firstItem?->quantity ?? 1), 4)
                : null,
            'rate_per_gram' => null,
            'issued_at' => now(),
        ]);

        $this->writeFile($invoice);

        return $invoice->fresh();
    }

    public function generateForOldGoldBooking(OldGoldBooking $booking): ?Invoice
    {
        if (! in_array($booking->status, ['completed', 'picked_up'], true)) {
            return null;
        }

        if ($existing = Invoice::query()->where('old_gold_booking_id', $booking->id)->first()) {
            $this->writeFile($existing);

            return $existing->fresh();
        }

        $booking->loadMissing(['user', 'payment']);
        $invoiceNumber = $this->nextInvoiceNumber();
        $amount = (float) ($booking->final_amount ?? $booking->quoted_amount ?? 0);

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'user_id' => $booking->user_id,
            'old_gold_booking_id' => $booking->id,
            'subtotal' => $amount,
            'gst_amount' => 0,
            'total_amount' => $amount,
            'metal_type' => $booking->metal_type,
            'quantity_grams' => $booking->estimated_weight_grams,
            'rate_per_gram' => $booking->rate_per_gram,
            'issued_at' => now(),
        ]);

        $this->writeFile($invoice);

        return $invoice->fresh();
    }

    public function writeFile(Invoice $invoice): void
    {
        $invoice->loadMissing([
            'user',
            'investment.user',
            'jewelleryOrder.user',
            'jewelleryOrder.items.product',
            'jewelleryOrder.payment',
            'jewelleryOrder.emiInstallments',
            'oldGoldBooking.user',
            'oldGoldBooking.payment',
        ]);

        $appName = $this->settings->get('app_name', 'HOXTAN');
        $supportEmail = $this->settings->get('support_email', '');
        $supportPhone = $this->settings->get('support_phone', '');

        if ($invoice->jewellery_order_id && $invoice->jewelleryOrder) {
            $html = View::make('invoices.jewellery', [
                'invoice' => $invoice,
                'order' => $invoice->jewelleryOrder,
                'user' => $invoice->jewelleryOrder->user ?? $invoice->user,
                'appName' => $appName,
                'supportEmail' => $supportEmail,
                'supportPhone' => $supportPhone,
            ])->render();
        } elseif ($invoice->old_gold_booking_id && $invoice->oldGoldBooking) {
            $html = View::make('invoices.sell-jewellery', [
                'invoice' => $invoice,
                'booking' => $invoice->oldGoldBooking,
                'user' => $invoice->oldGoldBooking->user ?? $invoice->user,
                'appName' => $appName,
                'supportEmail' => $supportEmail,
                'supportPhone' => $supportPhone,
            ])->render();
        } elseif ($invoice->investment_id && $invoice->investment) {
            $html = View::make('invoices.purchase', [
                'invoice' => $invoice,
                'investment' => $invoice->investment,
                'user' => $invoice->investment->user ?? $invoice->user,
                'appName' => $appName,
                'supportEmail' => $supportEmail,
                'supportPhone' => $supportPhone,
            ])->render();
        } else {
            return;
        }

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $path = 'invoices/'.$invoice->invoice_number.'.pdf';

        $legacyHtml = 'invoices/'.$invoice->invoice_number.'.html';
        if (Storage::disk('local')->exists($legacyHtml)) {
            Storage::disk('local')->delete($legacyHtml);
        }

        if ($invoice->file_path
            && $invoice->file_path !== $path
            && Storage::disk('local')->exists($invoice->file_path)
        ) {
            Storage::disk('local')->delete($invoice->file_path);
        }

        Storage::disk('local')->put($path, $pdf->output());
        $invoice->update(['file_path' => $path]);
    }

    public function isJewelleryOrderFullyPaid(JewelleryOrder $order): bool
    {
        if ($order->isEmi()) {
            return $order->emiInstallmentsFullyPaid();
        }

        $order->loadMissing('payment');

        return $order->payment?->status === 'completed';
    }

    public function getDownloadPath(Invoice $invoice): ?string
    {
        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            return null;
        }

        return Storage::disk('local')->path($invoice->file_path);
    }

    /**
     * @return array{
     *     invoice_number: string,
     *     source_type: string,
     *     investment_reference: ?string,
     *     order_number: ?string,
     *     metal_type: ?string,
     *     quantity_grams: ?float,
     *     total_amount: float,
     *     issued_at: ?string,
     *     download_url: string
     * }
     */
    public function apiPayload(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'investment:id,reference_id,type',
            'jewelleryOrder:id,order_number',
            'oldGoldBooking:id,booking_number',
        ]);

        return [
            'invoice_number' => $invoice->invoice_number,
            'source_type' => $invoice->sourceType(),
            'investment_reference' => $invoice->investment?->reference_id,
            'order_number' => $invoice->jewelleryOrder?->order_number,
            'booking_number' => $invoice->oldGoldBooking?->booking_number,
            'metal_type' => $invoice->metal_type,
            'quantity_grams' => $invoice->quantity_grams !== null ? (float) $invoice->quantity_grams : null,
            'total_amount' => (float) $invoice->total_amount,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'download_url' => route('api.invoices.download', $invoice),
        ];
    }

    protected function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $count = Invoice::query()->whereDate('created_at', today())->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
