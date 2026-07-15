<?php

namespace App\Http\Controllers\Admin;

use App\Services\FilamentImmediateExportService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemporaryExportDownloadController
{
    public function __invoke(
        string $token,
        FilamentImmediateExportService $exports,
    ): BinaryFileResponse|StreamedResponse {
        $payload = cache()->pull($exports->cacheKey($token));

        if (! is_array($payload) || empty($payload['path']) || empty($payload['filename'])) {
            abort(404, 'Export file expired or not found.');
        }

        $adminId = Filament::auth()->id() ?? auth('admin')->id();

        if (
            isset($payload['admin_id'])
            && $adminId !== null
            && (string) $payload['admin_id'] !== (string) $adminId
        ) {
            abort(403, 'This export link belongs to another admin.');
        }

        $disk = Storage::disk($payload['disk'] ?? 'local');

        if (! $disk->exists($payload['path'])) {
            abort(404, 'Export file not found.');
        }

        $absolutePath = $disk->path($payload['path']);

        // BinaryFileResponse supports deleteFileAfterSend; Storage::download() may
        // return StreamedResponse which does not.
        return response()
            ->download($absolutePath, $payload['filename'])
            ->deleteFileAfterSend(true);
    }
}
