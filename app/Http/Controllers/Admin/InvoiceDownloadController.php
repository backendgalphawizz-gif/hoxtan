<?php

namespace App\Http\Controllers\Admin;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceDownloadController
{
    public function __invoke(Invoice $invoice, InvoiceService $invoices): StreamedResponse|RedirectResponse
    {
        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            if ($invoice->jewellery_order_id && $invoice->jewelleryOrder) {
                $invoices->generateForJewelleryOrder($invoice->jewelleryOrder);
                $invoice->refresh();
            } elseif ($invoice->investment) {
                $invoices->generateForInvestment($invoice->investment);
                $invoice->refresh();
            }
        }

        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            abort(404, 'Invoice file not found.');
        }

        return Storage::disk('local')->download(
            $invoice->file_path,
            $invoice->invoice_number.'.html',
            ['Content-Type' => 'text/html'],
        );
    }
}
