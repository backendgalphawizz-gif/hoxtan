<?php

use App\Models\StaticPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $appName = config('app_content.app_name', 'HOXTAN');
        $supportEmail = config('app_content.website_support.email', 'support@hoxtandigigold.com');

        StaticPage::query()->updateOrCreate(
            ['slug' => 'cancel-policy'],
            [
                'title' => 'Cancel Policy',
                'is_published' => true,
                'content' => <<<HTML
<h2>Overview</h2>
<p>This Cancel Policy explains when and how you can cancel jewellery EMI orders, investment plans, and related requests on {$appName}. By using the app, you agree to these cancellation rules.</p>

<h2>Jewellery EMI Order Cancellation</h2>
<ul>
    <li>EMI jewellery orders can be cancelled only before delivery assignment / dispatch.</li>
    <li>On cancellation, a <strong>10% cancellation fee</strong> is deducted from the total EMI amount already paid.</li>
    <li>An additional <strong>3% GST</strong> is charged on that cancellation fee.</li>
    <li>The remaining amount is processed as a refund to your registered bank account after admin approval (or auto-approval within the stated SLA).</li>
    <li>Orders that are delivered, dispatched, or already in a driver’s possession cannot be cancelled under this policy.</li>
</ul>

<h2>Metal Withdrawals &amp; Holdings</h2>
<ul>
    <li>Purchased metal is subject to a holding period before withdrawal is allowed (as shown in the app).</li>
    <li>Once a withdrawal request is submitted, cancellations may not be possible after admin processing has started.</li>
</ul>

<h2>SIG / Investment Plans</h2>
<ul>
    <li>You may pause, resume, or stop an active SIG plan from the app subject to plan rules.</li>
    <li>Stopping a plan does not automatically reverse completed installments already credited to your holdings.</li>
</ul>

<h2>Refund Timeline</h2>
<p>Approved refunds are sent to the bank account submitted in your KYC. Processing time may vary by bank. {$appName} is not responsible for delays caused by incorrect bank details provided by the user.</p>

<h2>Contact</h2>
<p>For cancellation or refund questions, contact <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
HTML,
            ],
        );
    }

    public function down(): void
    {
        StaticPage::query()->where('slug', 'cancel-policy')->delete();
    }
};
