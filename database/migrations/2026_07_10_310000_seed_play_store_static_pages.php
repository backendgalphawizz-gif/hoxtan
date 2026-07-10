<?php

use App\Models\StaticPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $supportEmail = config('app_content.website_support.email', 'support@hoxtandigigold.com');
        $appName = config('app_content.app_name', 'HOXTAN');

        StaticPage::query()->updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'is_published' => true,
                'content' => <<<HTML
<h2>Introduction</h2>
<p>{$appName} (&quot;we&quot;, &quot;our&quot;, or &quot;us&quot;) operates the {$appName} mobile application and related services for buying, selling, and managing digital gold, silver, and jewellery. This Privacy Policy explains how we collect, use, store, and protect your personal information when you use our app and website.</p>
<p>By using {$appName}, you agree to the collection and use of information in accordance with this policy.</p>

<h2>Information We Collect</h2>
<ul>
    <li><strong>Account information:</strong> name, mobile number, email address, and profile photo.</li>
    <li><strong>Identity verification (KYC):</strong> PAN, Aadhaar details, selfie/face photo, and bank account information submitted for compliance.</li>
    <li><strong>Transaction data:</strong> gold/silver purchases, sales, jewellery orders, SIP investments, goals, and payment records.</li>
    <li><strong>Delivery information:</strong> shipping addresses and contact details for jewellery orders and pickups.</li>
    <li><strong>Device and usage data:</strong> device identifiers, IP address, app version, and logs required for security and fraud prevention.</li>
</ul>

<h2>How We Use Your Information</h2>
<ul>
    <li>To create and manage your account and authenticate access.</li>
    <li>To process transactions, orders, redemptions, and customer support requests.</li>
    <li>To comply with applicable laws, including KYC and anti-money laundering requirements.</li>
    <li>To improve app performance, security, and user experience.</li>
    <li>To send important service notifications related to your account or orders.</li>
</ul>

<h2>Data Sharing</h2>
<p>We do not sell your personal data. We may share information only with trusted service providers (payment gateways, logistics partners, KYC verification providers, and cloud hosting) strictly to operate our services, or when required by law.</p>

<h2>Data Security</h2>
<p>We use industry-standard safeguards including encrypted connections (TLS), secure authentication, and access controls to protect your information.</p>

<h2>Data Retention</h2>
<p>We retain account and transaction records as long as your account is active and as required by applicable law, tax, and regulatory obligations. When you delete your account, personal profile data is removed except where retention is legally required.</p>

<h2>Your Rights</h2>
<ul>
    <li>Access and update your profile information in the app.</li>
    <li>Request correction of inaccurate data.</li>
    <li>Delete your account from within the app (see our <a href="/delete-account">Delete Account</a> page).</li>
    <li>Contact us for privacy-related questions or requests.</li>
</ul>

<h2>Children</h2>
<p>Our services are not intended for users under 18 years of age.</p>

<h2>Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. Material changes will be reflected on this page with an updated date.</p>

<h2>Contact Us</h2>
<p>For privacy questions or data requests, email us at <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
HTML,
            ],
        );

        StaticPage::query()->updateOrCreate(
            ['slug' => 'delete-account'],
            [
                'title' => 'Delete Your Account',
                'is_published' => true,
                'content' => <<<HTML
<h2>Delete your {$appName} account</h2>
<p>You can permanently delete your {$appName} account and associated app profile data at any time.</p>

<h2>Delete from the app</h2>
<ol>
    <li>Open the {$appName} app and sign in.</li>
    <li>Go to <strong>Profile</strong>.</li>
    <li>Tap <strong>Close Account</strong> (or <strong>Delete Account</strong>).</li>
    <li>Enter your M-PIN to confirm.</li>
    <li>Your account will be closed immediately.</li>
</ol>

<h2>What is deleted</h2>
<ul>
    <li>Your user profile and login access.</li>
    <li>Saved addresses and app preferences.</li>
    <li>Active sessions and authentication tokens.</li>
    <li>Your profile photo stored on our servers.</li>
</ul>

<h2>What may be retained</h2>
<p>Some information may be retained where required by law or for legitimate business purposes, including:</p>
<ul>
    <li>Completed transaction, order, invoice, and tax records.</li>
    <li>KYC and compliance records for the period required by applicable regulations.</li>
    <li>Fraud prevention and security logs for a limited period.</li>
</ul>

<h2>Need help?</h2>
<p>If you cannot access the app or need assistance deleting your account, contact our support team at <a href="mailto:{$supportEmail}">{$supportEmail}</a> with the mobile number registered on your account. We will verify your identity before processing the request.</p>

<p><strong>App name:</strong> {$appName}<br>
<strong>Developer:</strong> {$appName}</p>
HTML,
            ],
        );
    }

    public function down(): void
    {
        StaticPage::query()->where('slug', 'delete-account')->delete();
    }
};
