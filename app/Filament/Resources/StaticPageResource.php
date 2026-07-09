<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\StaticPageResource\Pages;
use App\Models\StaticPage;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class StaticPageResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'static_pages';
    }

    protected static ?string $model = StaticPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CMS Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Static Pages';

    protected static ?string $modelLabel = 'Static Page';

    public static function form(Form $form): Form
    {
        $websitePresets = collect(config('app_content.website_pages', []))
            ->mapWithKeys(fn (array $page) => [$page['slug'] => $page['label']])
            ->all();

        return $form
            ->schema([
                Forms\Components\Section::make('Page Details')
                    ->description('Website pages use fixed slugs: about-us, team, terms-and-conditions, privacy-policy.')
                    ->schema([
                        Forms\Components\Select::make('website_preset')
                            ->label('Website Page Type')
                            ->options(['' => 'Custom page'] + $websitePresets)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) use ($websitePresets): void {
                                if (blank($state)) {
                                    return;
                                }

                                $set('slug', $state);
                                $set('title', $websitePresets[$state] ?? str($state)->headline()->toString());
                            })
                            ->dehydrated(false)
                            ->visible(fn ($livewire) => $livewire instanceof Pages\CreateStaticPage),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state, $livewire) {
                                if ($livewire instanceof Pages\CreateStaticPage) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Use about-us, team, terms-and-conditions, or privacy-policy for website pages.'),
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published')
                            ->default(false),
                        Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaticPages::route('/'),
            'create' => Pages\CreateStaticPage::route('/create'),
            'view' => Pages\ViewStaticPage::route('/{record}'),
            'edit' => Pages\EditStaticPage::route('/{record}/edit'),
        ];
    }
}
