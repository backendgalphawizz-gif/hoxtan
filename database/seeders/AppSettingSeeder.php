<?php

namespace Database\Seeders;

use App\Services\AppSettingService;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(AppSettingService::class);
        $definitions = config('app_settings.definitions', []);

        foreach ($definitions as $key => $definition) {
            $settings->set($key, $definition['default'] ?? '');
        }
    }
}
