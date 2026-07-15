<?php

namespace App\Services;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Livewire\Component;
use RuntimeException;

class FilamentImmediateExportService
{
    /**
     * Build the export synchronously, then download in-browser via a Blob.
     *
     * Avoids:
     * - Returning StreamedResponse from Livewire (stale CSRF / 419 Page Expired)
     * - A second authenticated GET download (session race → 419 / expired tab)
     *
     * @param  list<int|string>|null  $selectedKeys
     */
    public function download(
        Component $livewire,
        string $exporterClass,
        string $format = 'csv',
        ?array $selectedKeys = null,
    ): void {
        $format = ExportFormat::tryFrom($format)?->value ?? ExportFormat::Csv->value;
        $export = $this->buildExport($livewire, $exporterClass, $format, $selectedKeys);

        $payload = $this->buildBrowserDownloadPayload($export, $format);

        $livewire->js('(() => {
            try {
                const payload = '.json_encode($payload, JSON_THROW_ON_ERROR).';
                const binary = atob(payload.content);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                const blob = new Blob([bytes], { type: payload.mime });
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href = objectUrl;
                link.download = payload.filename;
                link.rel = "noopener";
                link.style.display = "none";
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            } catch (error) {
                console.error("Export download failed", error);
                window.alert("Export ready, but the browser could not start the download. Please try again.");
            }
        })()');

        Notification::make()
            ->title('Export ready')
            ->body('Your download should start shortly.')
            ->success()
            ->send();
    }

    /**
     * @return array{filename: string, mime: string, content: string}
     */
    protected function buildBrowserDownloadPayload(Export $export, string $format): array
    {
        $disk = $export->getFileDisk();
        $directory = $export->getFileDirectory();

        if (! $disk->exists($directory)) {
            throw new RuntimeException('Export file directory was not created.');
        }

        if ($format === ExportFormat::Xlsx->value) {
            $path = $directory.DIRECTORY_SEPARATOR.$export->file_name.'.xlsx';

            if (! $disk->exists($path)) {
                throw new RuntimeException('Excel export file was not created.');
            }

            return [
                'filename' => $export->file_name.'.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'content' => base64_encode($disk->get($path)),
            ];
        }

        $csv = (string) $disk->get($directory.DIRECTORY_SEPARATOR.'headers.csv');

        foreach ($disk->files($directory) as $file) {
            if (str($file)->endsWith('headers.csv') || ! str($file)->endsWith('.csv')) {
                continue;
            }

            $csv .= (string) $disk->get($file);
        }

        return [
            'filename' => $export->file_name.'.csv',
            'mime' => 'text/csv;charset=utf-8',
            'content' => base64_encode($csv),
        ];
    }

    /**
     * @param  list<int|string>|null  $selectedKeys
     */
    protected function buildExport(
        Component $livewire,
        string $exporterClass,
        string $format,
        ?array $selectedKeys = null,
    ): Export {
        if (! $livewire instanceof HasTable) {
            throw new \InvalidArgumentException('Export requires a table view.');
        }

        $format = ExportFormat::tryFrom($format)?->value ?? ExportFormat::Csv->value;

        $query = $exporterClass::modifyQuery($livewire->getTableQueryForExport());

        $records = null;

        if ($selectedKeys !== null && $selectedKeys !== []) {
            $selectedKeys = $this->normalizeSelectedKeys($selectedKeys);
            $query = (clone $query)->whereKey($selectedKeys);
            $records = $selectedKeys;
        }

        $columnMap = collect($exporterClass::getColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
            ->all();

        $export = app(Export::class);
        $export->user()->associate(Filament::auth()->user());
        $export->exporter = $exporterClass;
        $export->total_rows = $records !== null ? count($records) : $query->count();

        $exporter = $export->getExporter(
            columnMap: $columnMap,
            options: [],
        );

        $export->file_disk = $exporter->getFileDisk();
        $export->save();
        $export->file_name = $exporter->getFileName($export);
        $export->save();

        $serializedQuery = EloquentSerializeFacade::serialize($query);
        $export->unsetRelation('user');

        $chain = [
            Bus::batch([
                app(PrepareCsvExport::class, [
                    'export' => $export,
                    'query' => $serializedQuery,
                    'columnMap' => $columnMap,
                    'options' => [],
                    'chunkSize' => 100,
                    'records' => $records,
                ]),
            ])
                ->onConnection('sync')
                ->allowFailures(),
        ];

        if ($format === ExportFormat::Xlsx->value) {
            $chain[] = app(CreateXlsxFile::class, [
                'export' => $export,
                'columnMap' => $columnMap,
                'options' => [],
            ]);
        }

        Bus::chain($chain)
            ->onConnection('sync')
            ->dispatch();

        $export->refresh();
        $export->update([
            'completed_at' => now(),
            'successful_rows' => $export->total_rows,
            'processed_rows' => $export->total_rows,
        ]);

        return $export;
    }

    /**
     * @param  list<int|string|Model>  $selectedKeys
     * @return list<int|string>
     */
    protected function normalizeSelectedKeys(array $selectedKeys): array
    {
        return collect($selectedKeys)
            ->map(fn (mixed $key): mixed => $key instanceof Model ? $key->getKey() : $key)
            ->filter(fn (mixed $key): bool => $key !== null && $key !== '')
            ->values()
            ->all();
    }
}
