<?php

namespace App\Services;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

class FilamentImmediateExportService
{
    /**
     * Build the export file in-process (no Filament export jobs), then download
     * via an authenticated one-time token URL (no Laravel signed URLs).
     *
     * Signed URLs fail with INVALID SIGNATURE when APP_URL / HTTPS proxy
     * does not match the browser host. Cache tokens avoid that entirely.
     *
     * @param  list<int|string>|null  $selectedKeys
     */
    public function download(
        Component $livewire,
        string $exporterClass,
        string $format = 'csv',
        ?array $selectedKeys = null,
    ): void {
        if (! $livewire instanceof HasTable) {
            throw new \InvalidArgumentException('Export requires a table view.');
        }

        $format = ExportFormat::tryFrom($format)?->value ?? ExportFormat::Csv->value;
        $file = $this->writeExportFile($livewire, $exporterClass, $format, $selectedKeys);

        $url = route('admin.exports.download', ['token' => $file['token']], absolute: false);

        $livewire->js('(() => {
            const href = '.json_encode($url).';
            window.setTimeout(() => {
                const link = document.createElement("a");
                link.href = href;
                link.rel = "noopener";
                link.style.display = "none";
                document.body.appendChild(link);
                link.click();
                link.remove();
            }, 150);
        })()');

        Notification::make()
            ->title('Export ready')
            ->body('Your download should start shortly.')
            ->success()
            ->send();
    }

    /**
     * @param  list<int|string>|null  $selectedKeys
     * @return array{token: string, filename: string, path: string}
     */
    protected function writeExportFile(
        HasTable&Component $livewire,
        string $exporterClass,
        string $format,
        ?array $selectedKeys,
    ): array {
        /** @var class-string<Exporter> $exporterClass */
        $query = $exporterClass::modifyQuery($livewire->getTableQueryForExport());

        if ($selectedKeys !== null && $selectedKeys !== []) {
            $query = (clone $query)->whereKey($this->normalizeSelectedKeys($selectedKeys));
        }

        $columns = $exporterClass::getColumns();
        $columnMap = collect($columns)
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
            ->all();

        $headers = array_values($columnMap);

        // Unsaved Export is only used to instantiate the exporter for column formatting.
        // Do NOT persist it or run Filament export jobs (those call auth()->login()).
        $export = new Export([
            'exporter' => $exporterClass,
            'file_name' => Str::slug(class_basename($exporterClass)).'-'.now()->format('Ymd-His'),
            'file_disk' => 'local',
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
        ]);

        /** @var Exporter $exporter */
        $exporter = $export->getExporter($columnMap, []);

        foreach ($exporter->getCachedColumns() as $column) {
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }

        $token = (string) Str::uuid();
        $directory = 'admin-exports';
        $extension = $format === ExportFormat::Xlsx->value ? 'xlsx' : 'csv';
        $filename = $export->file_name.'.'.$extension;
        $relativePath = $directory.'/'.$token.'.'.$extension;

        Storage::disk('local')->makeDirectory($directory);

        $absolutePath = Storage::disk('local')->path($relativePath);

        if ($format === ExportFormat::Xlsx->value) {
            $this->writeXlsx($absolutePath, $headers, $query, $exporter);
        } else {
            $this->writeCsv($absolutePath, $headers, $query, $exporter);
        }

        $admin = Filament::auth()->user();

        // One-time download token tied to the admin who started the export.
        cache()->put($this->cacheKey($token), [
            'path' => $relativePath,
            'filename' => $filename,
            'disk' => 'local',
            'admin_id' => $admin?->getAuthIdentifier(),
        ], now()->addMinutes(15));

        return [
            'token' => $token,
            'filename' => $filename,
            'path' => $relativePath,
        ];
    }

    protected function writeCsv(string $absolutePath, array $headers, $query, Exporter $exporter): void
    {
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to create CSV export file.');
        }

        // UTF-8 BOM helps Excel open international characters correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        $keyName = $query->getModel()->getKeyName();

        $query->orderBy($keyName)->chunk(200, function ($records) use ($handle, $exporter): void {
            foreach ($records as $record) {
                fputcsv($handle, array_map(
                    fn (mixed $value): string => $this->stringifyCell($value),
                    $exporter($record),
                ));
            }
        });

        fclose($handle);
    }

    protected function writeXlsx(string $absolutePath, array $headers, $query, Exporter $exporter): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($absolutePath);
        $writer->addRow(Row::fromValues($headers));

        $keyName = $query->getModel()->getKeyName();

        $query->orderBy($keyName)->chunk(200, function ($records) use ($writer, $exporter): void {
            foreach ($records as $record) {
                $writer->addRow(Row::fromValues(array_map(
                    fn (mixed $value): string => $this->stringifyCell($value),
                    $exporter($record),
                )));
            }
        });

        $writer->close();
    }

    protected function stringifyCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => $this->stringifyCell($item), $value));
        }

        return (string) $value;
    }

    public function cacheKey(string $token): string
    {
        return 'admin-export:'.$token;
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
