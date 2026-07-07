<?php

namespace App\Policies;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;

class ExportPolicy
{
    public function view(?Authenticatable $user, Export $export): bool
    {
        if ($user === null) {
            return false;
        }

        return (int) $export->user_id === (int) $user->getKey();
    }
}
