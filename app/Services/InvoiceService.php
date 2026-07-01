<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Invoice;
use App\Models\User;
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

        if (Invoice::query()->where('investment_id', $investment->id)->exists()) {
            return Invoice::query()->where('investment_id', $investment->id)->first();
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

        $html = View::make('invoices.purchase', [
            'invoice' => $invoice,
            'investment' => $investment,
            'user' => $investment->user,
            'appName' => $this->settings->get('app_name', 'HOXTAN'),
            'supportEmail' => $this->settings->get('support_email', ''),
            'supportPhone' => $this->settings->get('support_phone', ''),
        ])->render();

        $path = 'invoices/'.$invoiceNumber.'.html';
        Storage::disk('local')->put($path, $html);
        $invoice->update(['file_path' => $path]);

        return $invoice;
    }

    public function getDownloadPath(Invoice $invoice): ?string
    {
        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            return null;
        }

        return Storage::disk('local')->path($invoice->file_path);
    }

    protected function nextInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd');
        $count = Invoice::query()->whereDate('created_at', today())->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
