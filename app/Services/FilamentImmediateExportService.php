<?php

namespace App\Services;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilamentImmediateExportService
{
    /**
     * @param  list<int|string>|null  $selectedKeys
     */
    public function download(
        Component $livewire,
        string $exporterClass,
        string $format = 'csv',
        ?array $selectedKeys = null,
    ): StreamedResponse {
        $export = $this->buildExport($livewire, $exporterClass, $format, $selectedKeys);

        $format = ExportFormat::tryFrom($format)?->value ?? ExportFormat::Csv->value;

        return ExportFormat::from($format)->getDownloader()($export);
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
