<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AppSettingSeeder::class,
            AdminRoleSeeder::class,
            AdminSeeder::class,
            DummyDataSeeder::class,
        ]);
    }
}
