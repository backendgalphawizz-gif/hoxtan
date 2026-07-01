<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; padding: 2rem; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #ea580c; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .brand { font-size: 1.5rem; font-weight: bold; color: #ea580c; }
        .meta { text-align: right; font-size: 0.875rem; color: #6b7280; }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #e5e7eb; padding: 0.75rem; text-align: left; }
        th { background: #f9fafb; font-size: 0.75rem; text-transform: uppercase; }
        .totals { margin-top: 1rem; width: 100%; max-width: 320px; margin-left: auto; }
        .totals td { border: none; padding: 0.35rem 0; }
        .totals .grand { font-weight: bold; font-size: 1.1rem; border-top: 1px solid #e5e7eb; padding-top: 0.5rem; }
        .footer { margin-top: 2rem; font-size: 0.8rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ $appName }}</div>
            <div>Purchase Tax Invoice</div>
        </div>
        <div class="meta">
            <div><strong>Invoice:</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Date:</strong> {{ $invoice->issued_at->format('d M Y') }}</div>
            <div><strong>Reference:</strong> {{ $investment->reference_id }}</div>
        </div>
    </div>

    <h1>Bill To</h1>
    <p>
        <strong>{{ $user->name }}</strong><br>
        Mobile: {{ $user->phone }}<br>
        @if($user->email && ! str_ends_with($user->email, '@hoxtan.app'))
            Email: {{ $user->email }}<br>
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty (g)</th>
                <th>Rate (₹/g)</th>
                <th>Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ ucfirst($invoice->metal_type) }} Purchase</td>
                <td>{{ number_format((float) $invoice->quantity_grams, 4) }}</td>
                <td>{{ number_format((float) $invoice->rate_per_gram, 2) }}</td>
                <td>{{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td style="text-align:right">₹{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td>GST</td><td style="text-align:right">₹{{ number_format((float) $invoice->gst_amount, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td style="text-align:right">₹{{ number_format((float) $invoice->total_amount, 2) }}</td></tr>
    </table>

    <div class="footer">
        @if($supportEmail) Support: {{ $supportEmail }} @endif
        @if($supportPhone) | {{ $supportPhone }} @endif
        <br>This is a computer-generated invoice from {{ $appName }}.
    </div>
</body>
</html>
