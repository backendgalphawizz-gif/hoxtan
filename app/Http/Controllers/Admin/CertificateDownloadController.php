<?php

namespace App\Http\Controllers\Admin;

use App\Models\HoldingCertificate;
use App\Services\HoldingCertificateService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateDownloadController
{
    public function __invoke(
        HoldingCertificate $certificate,
        HoldingCertificateService $certificates,
    ): StreamedResponse {
        $investment = $certificate->investment()->with('user')->firstOrFail();

        $certificates->writeFile($certificate, $investment, $investment->user);
        $certificate->refresh();

        if (! $certificate->file_path || ! Storage::disk('local')->exists($certificate->file_path)) {
            abort(404, 'Certificate file not found.');
        }

        return Storage::disk('local')->download(
            $certificate->file_path,
            $certificate->certificate_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
