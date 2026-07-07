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
use Illuminate\Support\Facades\Bus;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilamentImmediateExportService
{
    public function download(Component $livewire, string $exporterClass, string $format = 'csv'): StreamedResponse
    {
        $export = $this->buildExport($livewire, $exporterClass, $format);

        $format = ExportFormat::tryFrom($format)?->value ?? ExportFormat::Csv->value;

        return ExportFormat::from($format)->getDownloader()($export);
    }

    protected function buildExport(Component $livewire, string $exporterClass, string $format): Export
    {
        if (! $livewire instanceof HasTable) {
            throw new \InvalidArgumentException('Export requires a table view.');
        }

        $format = ExportFormat::tryFrom($format)?->value ?? ExportFormat::Csv->value;

        $query = $exporterClass::modifyQuery($livewire->getTableQueryForExport());

        $columnMap = collect($exporterClass::getColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
            ->all();

        $export = app(Export::class);
        $export->user()->associate(Filament::auth()->user());
        $export->exporter = $exporterClass;
        $export->total_rows = $query->count();

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
                    'records' => null,
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
}
