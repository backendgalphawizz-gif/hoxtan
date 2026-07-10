<?php

use App\Models\StaticPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $supportEmail = config('app_content.website_support.email', 'support@hoxtandigigold.com');
        $appName = config('app_content.app_name', 'HOXTAN');

        $userPrivacy = StaticPage::query()->where('slug', 'privacy-policy')->first();
        $userTerms = StaticPage::query()->where('slug', 'terms-and-conditions')->first();

        StaticPage::query()->updateOrCreate(
            ['slug' => 'user-privacy-policy'],
            [
                'title' => 'User Privacy Policy',
                'is_published' => true,
                'content' => $userPrivacy?->content ?? '<h2>Privacy Policy</h2><p>We respect your privacy and protect your personal data.</p>',
            ],
        );

        StaticPage::query()->updateOrCreate(
            ['slug' => 'user-terms-and-conditions'],
            [
                'title' => 'User Terms & Conditions',
                'is_published' => true,
                'content' => $userTerms?->content ?? '<h2>Terms & Conditions</h2><p>Please read these terms carefully before using the app.</p>',
            ],
        );

        StaticPage::query()->updateOrCreate(
            ['slug' => 'driver-privacy-policy'],
            [
                'title' => 'Driver Privacy Policy',
                'is_published' => true,
                'content' => <<<HTML
<h2>Introduction</h2>
<p>This Driver Privacy Policy explains how {$appName} collects, uses, and protects information from drivers using the {$appName} Driver app.</p>

<h2>Information We Collect</h2>
<ul>
    <li>Driver profile details: name, phone, email, vehicle information, and identity documents.</li>
    <li>Location and delivery/pickup task data required to complete assigned orders.</li>
    <li>Proof images, OTP verification logs, and task status updates.</li>
    <li>Device and app usage data for security and performance monitoring.</li>
</ul>

<h2>How We Use Driver Data</h2>
<ul>
    <li>To assign and manage jewellery delivery and pickup tasks.</li>
    <li>To verify identity, vehicle details, and task completion.</li>
    <li>To provide customer support and resolve delivery disputes.</li>
    <li>To comply with legal, safety, and fraud-prevention requirements.</li>
</ul>

<h2>Contact</h2>
<p>For driver privacy questions, contact <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
HTML,
            ],
        );

        StaticPage::query()->updateOrCreate(
            ['slug' => 'driver-terms-and-conditions'],
            [
                'title' => 'Driver Terms & Conditions',
                'is_published' => true,
                'content' => <<<HTML
<h2>Driver Agreement</h2>
<p>By using the {$appName} Driver app, you agree to these Driver Terms & Conditions.</p>

<h2>Driver Responsibilities</h2>
<ul>
    <li>Complete assigned deliveries and pickups professionally and on time.</li>
    <li>Verify customer identity, jewellery details, and OTP before handover or collection.</li>
    <li>Upload accurate proof images when required by the app workflow.</li>
    <li>Maintain valid vehicle documents and follow applicable traffic and safety laws.</li>
</ul>

<h2>Payments & Conduct</h2>
<p>Driver payouts, penalties, and service standards may be updated by {$appName} from time to time. Misuse of the platform, fraud, or repeated failed tasks may lead to suspension.</p>

<h2>Support</h2>
<p>For help, contact <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
HTML,
            ],
        );
    }

    public function down(): void
    {
        StaticPage::query()->whereIn('slug', [
            'user-privacy-policy',
            'user-terms-and-conditions',
            'driver-privacy-policy',
            'driver-terms-and-conditions',
        ])->delete();
    }
};
