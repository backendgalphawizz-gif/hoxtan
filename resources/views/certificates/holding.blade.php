<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate {{ $certificate->certificate_number }}</title>
    <style>
        :root {
            --cert-maroon: #8f1932;
            --cert-gold: #c5a059;
            --cert-gold-light: #e8d4a8;
            --cert-navy: #003366;
            --cert-text: #1a1a1a;
            --cert-muted: #666666;
            --cert-border: #d9d9d9;
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
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--cert-text);
            background: #ffffff;
            font-size: 12px;
            line-height: 1.45;
        }

        .certificate {
            position: relative;
            min-height: 100vh;
            padding: 2.25rem 3.25rem 2rem;
            background: #ffffff;
        }

        .certificate::before,
        .certificate::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 1.35rem;
            background: var(--cert-maroon);
        }

        .certificate::before {
            left: 0;
        }

        .certificate::after {
            right: 0;
        }

        .certificate__inner {
            position: relative;
            z-index: 1;
        }

        .certificate__pattern {
            position: absolute;
            top: 0.5rem;
            right: 0;
            width: 7.5rem;
            height: 7.5rem;
            opacity: 0.35;
            pointer-events: none;
        }

        .certificate__pattern span {
            position: absolute;
            display: block;
            border: 2px solid var(--cert-gold-light);
            background: rgba(197, 160, 89, 0.08);
        }

        .certificate__pattern span:nth-child(1) {
            width: 3.5rem;
            height: 3.5rem;
            top: 0;
            right: 0;
        }

        .certificate__pattern span:nth-child(2) {
            width: 3rem;
            height: 3rem;
            top: 1.25rem;
            right: 2.25rem;
        }

        .certificate__pattern span:nth-child(3) {
            width: 2.5rem;
            height: 2.5rem;
            top: 2.75rem;
            right: 0.5rem;
        }

        .certificate__brand {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .certificate__brand img {
            max-height: 3.25rem;
            width: auto;
            display: inline-block;
        }

        .certificate__brand-fallback {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .certificate__brand-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 9999px;
            border: 2px solid var(--cert-gold);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--cert-gold);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .certificate__brand-name {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--cert-gold);
        }

        .certificate__title {
            margin: 0 0 1.5rem;
            text-align: center;
            font-family: "Times New Roman", Times, serif;
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #111111;
        }

        .certificate__meta {
            margin-bottom: 1.35rem;
            font-size: 12px;
        }

        .certificate__meta-row {
            margin-bottom: 0.2rem;
        }

        .certificate__meta-row strong {
            font-weight: 700;
        }

        .certificate__intro {
            margin: 0 0 1rem;
            text-align: center;
            font-size: 13px;
        }

        table.particulars {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.35rem;
            font-size: 12px;
        }

        table.particulars th,
        table.particulars td {
            border: 1px solid var(--cert-border);
            padding: 0.55rem 0.7rem;
            text-align: left;
            vertical-align: top;
        }

        table.particulars th {
            width: 52%;
            background: #f3f3f3;
            font-weight: 700;
        }

        .certificate__partners {
            display: table;
            width: 100%;
            margin: 1.5rem 0 1.25rem;
            table-layout: fixed;
        }

        .certificate__partner {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 1rem;
        }

        .certificate__partner-logo {
            max-height: 2.5rem;
            width: auto;
            margin-bottom: 0.35rem;
        }

        .certificate__partner-brand {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--cert-gold);
            margin-bottom: 0.2rem;
        }

        .certificate__partner-brand--navy {
            color: var(--cert-navy);
            letter-spacing: 0.02em;
        }

        .certificate__partner-brand--navy::before {
            content: '|||';
            display: inline-block;
            margin-right: 0.35rem;
            font-weight: 700;
            letter-spacing: -0.08em;
        }

        .certificate__partner-tagline {
            font-size: 11px;
            color: var(--cert-text);
        }

        .certificate__narrative {
            margin: 0 0 0.9rem;
            text-align: justify;
            font-size: 11.5px;
        }

        .certificate__parties {
            display: table;
            width: 100%;
            margin-top: 1.5rem;
            table-layout: fixed;
        }

        .certificate__party {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 1.5rem;
            font-size: 11px;
        }

        .certificate__party:last-child {
            padding-right: 0;
            padding-left: 1.5rem;
        }

        .certificate__party-logo {
            max-height: 1.75rem;
            width: auto;
            margin-bottom: 0.45rem;
        }

        .certificate__party-brand {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--cert-maroon);
            margin-bottom: 0.45rem;
            letter-spacing: 0.03em;
        }

        .certificate__party-brand--navy {
            color: var(--cert-navy);
        }

        .certificate__party-brand--navy::before {
            content: '|||';
            display: inline-block;
            margin-right: 0.25rem;
            letter-spacing: -0.08em;
        }

        .certificate__party h3 {
            margin: 0 0 0.35rem;
            font-size: 11px;
            font-weight: 700;
            text-decoration: underline;
        }

        .certificate__party .name {
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .certificate__footer-note {
            margin-top: 1.75rem;
            font-size: 11px;
            color: var(--cert-muted);
            font-style: italic;
            text-align: left;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate__inner">
            <div class="certificate__pattern" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <div class="certificate__brand">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $appName }}">
                @else
                    <div class="certificate__brand-fallback">
                        <span class="certificate__brand-icon">H</span>
                        <span class="certificate__brand-name">{{ strtoupper($appName) }}</span>
                    </div>
                @endif
            </div>

            <h1 class="certificate__title">PROOF OF HOLDING CERTIFICATE</h1>

            <div class="certificate__meta">
                <div class="certificate__meta-row">
                    <strong>Certificate No.:</strong> {{ $certificate->certificate_number }}
                </div>
                <div class="certificate__meta-row">
                    <strong>Date of Issue:</strong> {{ $issuedAtDisplay }}
                </div>
            </div>

            <p class="certificate__intro">This is to certify your {{ $providerLabel }} holdings:</p>

            <table class="particulars">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Account Holder Name</td>
                        <td>{{ $certificate->account_holder_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ $holdingLabel }} (as of {{ $issuedAtDisplay }})</td>
                        <td>{{ $holdingDisplay }}</td>
                    </tr>
                    <tr>
                        <td>Purity</td>
                        <td>{{ $certificate->purity }}</td>
                    </tr>
                    <tr>
                        <td>Frequency of Physical Vault Audit</td>
                        <td>{{ $vaultAuditFrequency }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="certificate__partners">
                <div class="certificate__partner">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $appName }}" class="certificate__partner-logo">
                    @else
                        <div class="certificate__partner-brand">{{ strtoupper($appName) }}</div>
                    @endif
                    <div class="certificate__partner-tagline">{{ $brandTagline }}</div>
                </div>
                <div class="certificate__partner">
                    @if ($custodianLogo)
                        <img src="{{ $custodianLogo }}" alt="{{ $custodian['name'] ?? 'Custodian' }}" class="certificate__partner-logo">
                    @else
                        <div class="certificate__partner-brand certificate__partner-brand--navy">
                            {{ strtoupper($custodian['name'] ?? 'CUSTODIAN') }}
                        </div>
                    @endif
                    <div class="certificate__partner-tagline">{{ $custodian['tagline'] ?? 'Custodian Vault' }}</div>
                </div>
            </div>

            <p class="certificate__narrative">
                {{ $custodyNote }}
            </p>

            <p class="certificate__narrative">
                {{ $trusteeNote }}
            </p>

            <div class="certificate__parties">
                <div class="certificate__party">
                    @if ($trusteeLogo)
                        <img src="{{ $trusteeLogo }}" alt="{{ $trustee['name'] ?? 'Trustee' }}" class="certificate__party-logo">
                    @else
                        <div class="certificate__party-brand">{{ strtoupper($trustee['name'] ?? 'TRUSTEE') }}</div>
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
                </div>
                <div class="certificate__party">
                    @if ($custodianLogo)
                        <img src="{{ $custodianLogo }}" alt="{{ $custodian['name'] ?? 'Custodian' }}" class="certificate__party-logo">
                    @else
                        <div class="certificate__party-brand certificate__party-brand--navy">
                            {{ strtoupper($custodian['name'] ?? 'CUSTODIAN') }}
                        </div>
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
                </div>
            </div>

            <p class="certificate__footer-note">(This document is system-generated and does not require a signature.)</p>
        </div>
    </div>
</body>
</html>
