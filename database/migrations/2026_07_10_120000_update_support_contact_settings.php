<?php

use App\Models\AppSetting;
use App\Services\AppSettingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $support = config('app_content.website_support', []);
        $email = (string) ($support['email'] ?? 'support@hoxtandigigold.com');
        $phone = (string) ($support['toll_free'] ?? '18005693934');

        AppSetting::query()->updateOrCreate(
            ['key' => 'support_email'],
            [
                'value' => $email,
                'group' => 'general',
                'label' => 'Support Email',
                'type' => 'email',
                'description' => 'Contact email shown to users.',
            ],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'support_phone'],
            [
                'value' => $phone,
                'group' => 'general',
                'label' => 'Support Phone (Toll Free)',
                'type' => 'text',
                'description' => 'Toll-free support number shown in website footer and app.',
            ],
        );

        app(AppSettingService::class)->forgetCache();
    }

    public function down(): void
    {
        // No rollback — contact details are intentional product config.
    }
};
