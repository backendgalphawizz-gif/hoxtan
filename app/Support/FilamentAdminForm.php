<?php

namespace App\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class FilamentAdminForm
{
    public static function configureRequiredFields(): void
    {
        Field::configureUsing(function (Field $field): void {
            if ($field->isRequired()) {
                $field->markAsRequired();
            }
        });

        foreach ([TextInput::class, Textarea::class, Select::class] as $componentClass) {
            $componentClass::configureUsing(function ($component): void {
                if (! method_exists($component, 'isRequired') || ! $component->isRequired()) {
                    return;
                }

                if (method_exists($component, 'extraInputAttributes')) {
                    $component->extraInputAttributes(['required' => 'required'], merge: true);
                }
            });
        }
    }
}
