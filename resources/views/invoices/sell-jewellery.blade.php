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
            <div>Sell Jewellery Settlement Invoice</div>
        </div>
        <div class="meta">
            <div><strong>Invoice:</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Date:</strong> {{ $invoice->issued_at->format('d M Y') }}</div>
            <div><strong>Request:</strong> {{ $booking->booking_number }}</div>
        </div>
    </div>

    <h1>Customer</h1>
    <p>
        <strong>{{ $user->name }}</strong><br>
        Mobile: {{ $user->phone }}<br>
        @if($user->email && ! str_ends_with($user->email, '@hoxtan.app'))
            Email: {{ $user->email }}<br>
        @endif
        @if($booking->pickup_address)
            Pickup: {{ $booking->pickup_name }} — {{ $booking->pickup_address }}<br>
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Particulars</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Item</td>
                <td>{{ $booking->item_name ?? 'Old Jewellery' }}</td>
            </tr>
            <tr>
                <td>Metal</td>
                <td>{{ ucfirst((string) $booking->metal_type) }}</td>
            </tr>
            <tr>
                <td>Purity</td>
                <td>{{ $booking->purity ?? '—' }}</td>
            </tr>
            <tr>
                <td>Weight</td>
                <td>
                    @if($booking->estimated_weight_grams !== null)
                        {{ number_format((float) $booking->estimated_weight_grams, 3) }} g
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td>Rate / g</td>
                <td>
                    @if($booking->rate_per_gram !== null)
                        ₹{{ number_format((float) $booking->rate_per_gram, 2) }}
                    @else
                        —
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Quoted Amount</td>
            <td style="text-align:right;">₹{{ number_format((float) ($booking->quoted_amount ?? 0), 2) }}</td>
        </tr>
        <tr class="grand">
            <td>Settlement Amount</td>
            <td style="text-align:right;">₹{{ number_format((float) $invoice->total_amount, 2) }}</td>
        </tr>
    </table>

    <p class="footer">
        This is a system-generated settlement invoice for your sell jewellery request.
        @if($supportEmail || $supportPhone)
            Support: {{ collect([$supportEmail, $supportPhone])->filter()->implode(' | ') }}
        @endif
    </p>
</body>
</html>
