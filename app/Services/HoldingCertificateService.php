<?php

namespace App\Services;

use App\Models\HoldingCertificate;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class HoldingCertificateService
{
    public function __construct(
        protected AppSettingService $settings,
        protected UserHoldingsService $holdings,
    ) {}

    public function generateForInvestment(Investment $investment): ?HoldingCertificate
    {
        if ($investment->type !== 'buy' || $investment->status !== 'completed') {
            return null;
        }

        if ($existing = HoldingCertificate::query()->where('investment_id', $investment->id)->first()) {
            return $existing;
        }

        $investment->loadMissing('user');
        $user = $investment->user;

        if (! $user) {
            return null;
        }

        $metalType = (string) $investment->metal_type;
        $metalConfig = config('holding_certificate.'.$metalType, config('holding_certificate.gold'));
        $issuedAt = now();
        $holdingGrams = $this->holdings->calculateMetalHoldings($user->id, $metalType);
        $certificateNumber = $this->nextCertificateNumber($issuedAt);

        $certificate = HoldingCertificate::create([
            'certificate_number' => $certificateNumber,
            'user_id' => $user->id,
            'investment_id' => $investment->id,
            'account_holder_name' => $user->name,
            'metal_type' => $metalType,
            'holding_grams' => $holdingGrams,
            'purity' => $metalConfig['purity'] ?? ($metalType === 'silver' ? '999' : '24K'),
            'issued_at' => $issuedAt,
        ]);

        $this->writeFile($certificate, $investment, $user);

        return $certificate->fresh();
    }

    public function writeFile(HoldingCertificate $certificate, Investment $investment, User $user): void
    {
        $metalType = $certificate->metal_type;
        $metalConfig = config('holding_certificate.'.$metalType, config('holding_certificate.gold'));
        $providerLabel = $metalConfig['provider_label'] ?? 'digital '.$metalType;
        $brand = config('holding_certificate.brand', []);
        $trustee = config('holding_certificate.trustee', []);
        $custodian = config('holding_certificate.custodian', []);

        $html = View::make('certificates.holding', [
            'certificate' => $certificate,
            'investment' => $investment,
            'user' => $user,
            'appName' => $brand['name'] ?? $this->settings->get('app_name', config('app_content.app_name', 'HOXTAN')),
            'brandTagline' => $brand['tagline'] ?? 'Digital Gold Provider',
            'brandLogo' => $this->embedPublicImage($brand['logo'] ?? 'images/hoxtan-logo.png'),
            'providerLabel' => $providerLabel,
            'holdingLabel' => $metalConfig['holding_label'] ?? (ucfirst($metalType).' Holding'),
            'metalLabel' => ucfirst($metalType),
            'vaultAuditFrequency' => config('holding_certificate.vault_audit_frequency', 'Annual'),
            'custodyNote' => config('holding_certificate.custody_note'),
            'trusteeNote' => config('holding_certificate.trustee_note'),
            'trustee' => $trustee,
            'custodian' => $custodian,
            'trusteeLogo' => $this->embedPublicImage($trustee['logo'] ?? null),
            'custodianLogo' => $this->embedPublicImage($custodian['logo'] ?? null),
            'holdingDisplay' => $this->formatGrams((float) $certificate->holding_grams),
            'issuedAtDisplay' => $certificate->issued_at?->format('d M Y'),
        ])->render();

        $path = 'certificates/'.$certificate->certificate_number.'.html';
        Storage::disk('local')->put($path, $html);
        $certificate->update(['file_path' => $path]);
    }

    public function getDownloadPath(HoldingCertificate $certificate): ?string
    {
        if (! $certificate->file_path || ! Storage::disk('local')->exists($certificate->file_path)) {
            return null;
        }

        return Storage::disk('local')->path($certificate->file_path);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(?HoldingCertificate $certificate): ?array
    {
        if (! $certificate) {
            return null;
        }

        return [
            'certificate_number' => $certificate->certificate_number,
            'issued_at' => $certificate->issued_at?->toIso8601String(),
            'issued_at_display' => $certificate->issued_at?->format('d M Y'),
            'account_holder_name' => $certificate->account_holder_name,
            'metal_type' => $certificate->metal_type,
            'holding_grams' => (float) $certificate->holding_grams,
            'holding_display' => $this->formatGrams((float) $certificate->holding_grams),
            'purity' => $certificate->purity,
            'download_url' => route('api.certificates.download', $certificate),
        ];
    }

    protected function nextCertificateNumber($issuedAt): string
    {
        $prefix = (string) config('holding_certificate.prefix', 'HXT-POH');
        $datePart = $issuedAt->format('ymd');
        $serial = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        $number = "{$prefix}-{$datePart}-{$serial}";

        while (HoldingCertificate::query()->where('certificate_number', $number)->exists()) {
            $serial = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $number = "{$prefix}-{$datePart}-{$serial}";
        }

        return $number;
    }

    protected function formatGrams(float $grams): string
    {
        $formatted = rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted).'g';
    }

    protected function embedPublicImage(?string $relativePath): ?string
    {
        if (blank($relativePath)) {
            return null;
        }

        $fullPath = public_path(ltrim($relativePath, '/'));

        if (! is_file($fullPath)) {
            return null;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($fullPath));
    }
}
