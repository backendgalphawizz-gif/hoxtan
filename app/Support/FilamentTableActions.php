<?php

namespace App\Support;

use Filament\Support\Enums\ActionSize;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;

class FilamentTableActions
{
    public static function view(): ViewAction
    {
        return ViewAction::make()
            ->icon('heroicon-o-eye')
            ->iconButton()
            ->color('gray')
            ->size(ActionSize::Small)
            ->tooltip('View');
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
            ->color('primary')
            ->size(ActionSize::Small)
            ->tooltip('Edit');
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->size(ActionSize::Small)
            ->tooltip('Delete');
    }

    public static function make(string $name): Action
    {
        return Action::make($name)
            ->iconButton()
            ->size(ActionSize::Small);
    }
}
