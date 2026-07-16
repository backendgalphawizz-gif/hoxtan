<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate {{ $certificate->certificate_number }}</title>
    <style>
        :root {
            --cert-frame: #d8a0ad;
            --cert-frame-dark: #8f1932;
            --cert-gold: #c5a059;
            --cert-gold-light: #e8d4a8;
            --cert-text: #111111;
            --cert-muted: #777777;
            --cert-border: #cccccc;
            --cert-table-head: #f0f0f0;
        }

        @page {
            margin: 0;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 18px;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--cert-text);
            background: #ffffff;
            font-size: 11.5px;
            line-height: 1.42;
        }

        .certificate-frame {
            position: relative;
            min-height: calc(100vh - 36px);
            border: 3px double var(--cert-frame);
            background: #ffffff;
            padding: 34px 42px 28px;
        }

        .certificate-frame__bar {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 14px;
            background: var(--cert-frame-dark);
        }

        .certificate-content {
            position: relative;
            padding-bottom: 18px;
        }

        .certificate-watermark {
            position: absolute;
            top: 0;
            right: 0;
            width: 110px;
            height: 110px;
            opacity: 0.28;
            pointer-events: none;
        }

        .certificate-watermark span {
            position: absolute;
            display: block;
            border: 1.5px solid var(--cert-gold-light);
            background: rgba(197, 160, 89, 0.06);
        }

        .certificate-watermark span:nth-child(1) {
            width: 52px;
            height: 52px;
            top: 0;
            right: 0;
        }

        .certificate-watermark span:nth-child(2) {
            width: 42px;
            height: 42px;
            top: 18px;
            right: 34px;
        }

        .certificate-watermark span:nth-child(3) {
            width: 34px;
            height: 34px;
            top: 42px;
            right: 8px;
        }

        .certificate-brand {
            text-align: center;
            margin: 0 0 18px;
        }

        .certificate-brand img {
            height: 42px;
            width: auto;
            display: inline-block;
        }

        .certificate-title {
            margin: 0 0 22px;
            text-align: center;
            font-family: "Times New Roman", Times, serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #000000;
        }

        .certificate-meta {
            margin-bottom: 16px;
            font-size: 11.5px;
        }

        .certificate-meta div {
            margin-bottom: 2px;
        }

        .certificate-meta strong {
            font-weight: 700;
        }

        .certificate-intro {
            margin: 0 0 12px;
            text-align: center;
            font-size: 12px;
        }

        table.particulars {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            font-size: 11.5px;
        }

        table.particulars th,
        table.particulars td {
            border: 1px solid var(--cert-border);
            padding: 7px 10px;
            text-align: left;
            vertical-align: top;
        }

        table.particulars thead th {
            background: var(--cert-table-head);
            font-weight: 700;
        }

        table.particulars tbody th {
            width: 54%;
            font-weight: 400;
            background: #ffffff;
        }

        .certificate-partners {
            width: 100%;
            margin: 24px 0 18px;
            border-collapse: collapse;
        }

        .certificate-partners td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 18px;
        }

        .certificate-partners img {
            height: 34px;
            width: auto;
            display: inline-block;
            margin-bottom: 6px;
        }

        .certificate-partners__label {
            font-size: 11px;
            font-weight: 700;
            color: var(--cert-text);
        }

        .certificate-narrative {
            margin: 0 0 10px;
            text-align: justify;
            font-size: 11px;
        }

        .certificate-parties {
            width: 100%;
            margin-top: 22px;
            border-collapse: collapse;
        }

        .certificate-parties td {
            width: 50%;
            vertical-align: top;
            padding-right: 22px;
            font-size: 10.5px;
        }

        .certificate-parties td:last-child {
            padding-right: 0;
            padding-left: 22px;
        }

        .certificate-parties img {
            height: 24px;
            width: auto;
            display: block;
            margin-bottom: 8px;
        }

        .certificate-parties h3 {
            margin: 0 0 4px;
            font-size: 10.5px;
            font-weight: 700;
            text-decoration: underline;
        }

        .certificate-parties .name {
            font-weight: 700;
            margin-bottom: 2px;
        }

        .certificate-footer-note {
            margin-top: 22px;
            font-size: 10.5px;
            color: var(--cert-muted);
            font-style: italic;
            text-align: center;
        }

        @media print {
            body {
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .certificate-frame {
                min-height: 100vh;
                border-width: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-frame">
        <div class="certificate-content">
            <div class="certificate-watermark" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <div class="certificate-brand">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $appName }}">
                @endif
            </div>

            <h1 class="certificate-title">PROOF OF HOLDING CERTIFICATE</h1>

            <div class="certificate-meta">
                <div><strong>Certificate No.:</strong> {{ $certificate->certificate_number }}</div>
                <div><strong>Date of Issue:</strong> {{ $issuedAtDisplay }}</div>
            </div>

            <p class="certificate-intro">This is to certify your {{ $providerLabel }} holdings:</p>

            <table class="particulars">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Account Holder Name</th>
                        <td>{{ $certificate->account_holder_name }}</td>
                    </tr>
                    <tr>
                        <th>{{ $holdingLabel }} (as of {{ $issuedAtDisplay }})</th>
                        <td>{{ $holdingDisplay }}</td>
                    </tr>
                    <tr>
                        <th>Purity</th>
                        <td>{{ $certificate->purity }}</td>
                    </tr>
                    <tr>
                        <th>Frequency of Physical Vault Audit</th>
                        <td>{{ $vaultAuditFrequency }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="certificate-partners">
                <tr>
                    <td>
                        @if ($brandLogo)
                            <img src="{{ $brandLogo }}" alt="{{ $appName }}">
                        @endif
                        <div class="certificate-partners__label">{{ $brandTagline }}</div>
                    </td>
                    <td>
                        @if ($custodianLogo)
                            <img src="{{ $custodianLogo }}" alt="{{ $custodian['name'] ?? 'Custodian' }}">
                        @endif
                        <div class="certificate-partners__label">{{ $custodian['tagline'] ?? 'Custodian Vault' }}</div>
                    </td>
                </tr>
            </table>

            <p class="certificate-narrative">{{ $custodyNote }}</p>
            <p class="certificate-narrative">{{ $trusteeNote }}</p>

            <table class="certificate-parties">
                <tr>
                    <td>
                        @if ($trusteeLogo)
                            <img src="{{ $trusteeLogo }}" alt="{{ $trustee['name'] ?? 'Trustee' }}">
                        @endif
                        <h3>{{ $trustee['title'] ?? 'Trustee Administrator Details' }}:</h3>
                        <div class="name">{{ $trustee['name'] ?? '' }}</div>
                        <div>Registered Office:</div>
                        @foreach (($trustee['registered_office_lines'] ?? []) as $line)
                            @if (filled($line))
                                <div>{{ $line }}</div>
                            @endif
                        @endforeach
                        @if (! empty($trustee['phone']))
                            <div>Phone : {{ $trustee['phone'] }}</div>
                        @endif
                        @if (! empty($trustee['cin']))
                            <div>CIN: {{ $trustee['cin'] }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($custodianLogo)
                            <img src="{{ $custodianLogo }}" alt="{{ $custodian['name'] ?? 'Custodian' }}">
                        @endif
                        <h3>{{ $custodian['title'] ?? 'Custodian Details' }}:</h3>
                        <div class="name">{{ $custodian['name'] ?? '' }}</div>
                        <div>Registered Office:</div>
                        @foreach (($custodian['registered_office_lines'] ?? []) as $line)
                            @if (filled($line))
                                <div>{{ $line }}</div>
                            @endif
                        @endforeach
                        @if (! empty($custodian['phone']))
                            <div>Phone : {{ $custodian['phone'] }}</div>
                        @endif
                        @if (! empty($custodian['cin']))
                            <div>CIN: {{ $custodian['cin'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <p class="certificate-footer-note">(This document is system-generated and does not require a signature.)</p>
        </div>

        <div class="certificate-frame__bar" aria-hidden="true"></div>
    </div>
</body>
</html>
