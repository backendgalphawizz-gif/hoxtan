<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate {{ $certificate->certificate_number }}</title>
    <style>
        @page { margin: 28mm 22mm; }
        body {
            font-family: "Times New Roman", Times, serif;
            color: #1a1a1a;
            margin: 0;
            padding: 2rem 2.5rem;
            line-height: 1.45;
            font-size: 14px;
        }
        .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            font-size: 13px;
        }
        .meta strong { font-weight: 700; }
        .brand {
            text-align: center;
            letter-spacing: 0.12em;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #8a6a1f;
        }
        .intro {
            margin: 0 0 1.25rem;
            font-size: 15px;
        }
        table.particulars {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.25rem;
        }
        table.particulars th,
        table.particulars td {
            border: 1px solid #333;
            padding: 0.55rem 0.75rem;
            text-align: left;
            vertical-align: top;
        }
        table.particulars th {
            width: 48%;
            background: #f5f5f5;
            font-weight: 600;
        }
        .narrative {
            margin: 0 0 1.25rem;
            text-align: justify;
        }
        .parties {
            display: flex;
            justify-content: space-between;
            gap: 2rem;
            margin-top: 1.5rem;
        }
        .party {
            width: 48%;
            font-size: 12.5px;
        }
        .party h3 {
            margin: 0 0 0.4rem;
            font-size: 13px;
            font-weight: 700;
            text-decoration: underline;
        }
        .party .name { font-weight: 700; margin-bottom: 0.25rem; }
        .footer-note {
            margin-top: 2rem;
            font-size: 12px;
            color: #444;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="brand">{{ $appName }}</div>

    <div class="meta">
        <div><strong>Certificate No.:</strong> {{ $certificate->certificate_number }}</div>
        <div><strong>Date of Issue:</strong> {{ $issuedAtDisplay }}</div>
    </div>

    <p class="intro">This is to certify your {{ $providerLabel }} holdings:</p>

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
                <td>{{ $metalLabel }} Holding (as of {{ $issuedAtDisplay }})</td>
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
            <tr>
                <td>Digital {{ $metalLabel }} Provider</td>
                <td>{{ $digitalProvider }}</td>
            </tr>
        </tbody>
    </table>

    <p class="narrative">
        This {{ $metalLabel }} was purchased at {{ $appName }}.
        {{ $custodyNote }}
    </p>

    <p class="narrative">
        The trustee administrator acts as an independent trustee responsible for protecting your interest
        and cross-verifies the details of the Auditor's physical metal report with daily Custodian reports
        as to {{ strtolower($metalLabel) }} balance in the vault. This certificate serves as an official
        confirmation of {{ strtolower($metalLabel) }} holdings and is intended for use solely in this capacity.
    </p>

    <div class="parties">
        <div class="party">
            <h3>{{ $trustee['title'] ?? 'Trustee Administrator Details' }}:</h3>
            <div class="name">{{ $trustee['name'] ?? '' }}</div>
            <div>Registered Office:</div>
            @foreach(($trustee['registered_office_lines'] ?? []) as $line)
                @if(filled($line))
                    <div>{{ $line }}</div>
                @endif
            @endforeach
            @if(!empty($trustee['phone']))
                <div>Phone : {{ $trustee['phone'] }}</div>
            @endif
            @if(!empty($trustee['cin']))
                <div>CIN: {{ $trustee['cin'] }}</div>
            @endif
        </div>
        <div class="party">
            <h3>{{ $custodian['title'] ?? 'Custodian Details' }}:</h3>
            <div class="name">{{ $custodian['name'] ?? '' }}</div>
            <div>Registered Office:</div>
            @foreach(($custodian['registered_office_lines'] ?? []) as $line)
                @if(filled($line))
                    <div>{{ $line }}</div>
                @endif
            @endforeach
            @if(!empty($custodian['phone']))
                <div>Phone : {{ $custodian['phone'] }}</div>
            @endif
            @if(!empty($custodian['cin']))
                <div>CIN: {{ $custodian['cin'] }}</div>
            @endif
        </div>
    </div>

    <p class="footer-note">(This document is system-generated and does not require a signature.)</p>
</body>
</html>
