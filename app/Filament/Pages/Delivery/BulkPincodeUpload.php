<?php

namespace App\Filament\Pages\Delivery;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Services\BlockedPincodeService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class BulkPincodeUpload extends Page implements HasForms
{
    use InteractsWithAdminPermissions;
    use InteractsWithForms;

    protected static function adminPermissionModule(): string
    {
        return 'blocked_pincodes';
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Delivery Management';

    protected static ?string $navigationLabel = 'Bulk Pincode Upload';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'admin.delivery.bulk-pincode-upload';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getSubheading(): ?string
    {
        return 'Upload a CSV/TXT file or paste pincodes to block delivery to those areas.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload File')
                    ->description('One pincode per line. Optional CSV columns: pincode, city, state, reason.')
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label('Pincode File')
                            ->disk('local')
                            ->directory('imports/blocked-pincodes')
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('pasted')
                            ->label('Or paste pincodes')
                            ->placeholder("560001\n560002\n560003, Bangalore, Karnataka, Out of service area")
                            ->rows(10)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function import(BlockedPincodeService $blockedPincodeService): void
    {
        $data = $this->form->getState();
        $contents = trim((string) ($data['pasted'] ?? ''));

        if (! empty($data['file'])) {
            $path = is_array($data['file']) ? reset($data['file']) : $data['file'];

            if ($path && Storage::disk('local')->exists($path)) {
                $contents = trim($contents."\n".Storage::disk('local')->get($path));
                Storage::disk('local')->delete($path);
            }
        }

        if ($contents === '') {
            Notification::make()
                ->title('No pincodes provided')
                ->body('Upload a file or paste pincodes before importing.')
                ->warning()
                ->send();

            return;
        }

        $result = $blockedPincodeService->importFromText($contents);

        Notification::make()
            ->title('Pincode import completed')
            ->body(sprintf(
                '%d imported, %d skipped (duplicates), %d invalid.',
                $result['imported'],
                $result['skipped'],
                $result['invalid'],
            ))
            ->success()
            ->send();

        $this->form->fill();
    }
}
