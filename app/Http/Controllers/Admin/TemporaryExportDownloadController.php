<?php

namespace App\Http\Controllers\Admin;

use App\Services\FilamentImmediateExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemporaryExportDownloadController
{
    public function __invoke(
        Request $request,
        string $token,
        FilamentImmediateExportService $exports,
    ): StreamedResponse {
        abort_unless($request->hasValidSignature(), 403);

        $payload = cache()->pull($exports->cacheKey($token));

        if (! is_array($payload) || empty($payload['path']) || empty($payload['filename'])) {
            abort(404, 'Export file expired or not found.');
        }

        $disk = Storage::disk($payload['disk'] ?? 'local');

        if (! $disk->exists($payload['path'])) {
            abort(404, 'Export file not found.');
        }

        return $disk->download(
            $payload['path'],
            $payload['filename'],
        )->deleteFileAfterSend(true);
    }
}
