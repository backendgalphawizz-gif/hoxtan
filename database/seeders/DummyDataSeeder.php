<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\DailyReport;
use App\Models\Faq;
use App\Models\GstRecord;
use App\Models\Investment;
use App\Models\InvestmentGoal;
use App\Models\KycDetail;
use App\Models\MetalRate;
use App\Models\Offer;
use App\Models\PushNotification;
use App\Models\Redemption;
use App\Models\StaticPage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\GstService;
use App\Support\PlaceholderImage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();

        // ── Users ──────────────────────────────────────────────
        $users = [
            [
                'name' => 'Rahul Sharma',
                'email' => 'rahul@example.com',
                'phone' => '9876543210',
                'role' => 'investor',
                'is_verified' => true,
                'kyc_status' => 'approved',
                'gold_holdings' => 12.5000,
                'silver_holdings' => 250.0000,
                'wallet_balance' => 45000.00,
            ],
            [
                'name' => 'Priya Patel',
                'email' => 'priya@example.com',
                'phone' => '9123456780',
                'role' => 'investor',
                'is_verified' => true,
                'kyc_status' => 'approved',
                'gold_holdings' => 8.7500,
                'silver_holdings' => 500.0000,
                'wallet_balance' => 32000.00,
            ],
            [
                'name' => 'Amit Kumar',
                'email' => 'amit@example.com',
                'phone' => '9988776655',
                'role' => 'investor',
                'is_verified' => false,
                'kyc_status' => 'under_review',
                'gold_holdings' => 0,
                'silver_holdings' => 0,
                'wallet_balance' => 5000.00,
            ],
            [
                'name' => 'Sneha Reddy',
                'email' => 'sneha@example.com',
                'phone' => '8765432109',
                'role' => 'user',
                'is_verified' => true,
                'kyc_status' => 'submitted',
                'gold_holdings' => 0,
                'silver_holdings' => 0,
                'wallet_balance' => 1500.00,
            ],
            [
                'name' => 'Vikram Singh',
                'email' => 'vikram@example.com',
                'phone' => '7654321098',
                'role' => 'investor',
                'is_verified' => true,
                'kyc_status' => 'approved',
                'gold_holdings' => 25.0000,
                'silver_holdings' => 1000.0000,
                'wallet_balance' => 85000.00,
                'is_blocked' => true,
                'block_reason' => 'Suspicious activity detected',
                'blocked_at' => now()->subDays(2),
            ],
            [
                'name' => 'Anita Desai',
                'email' => 'anita@example.com',
                'phone' => '6543210987',
                'role' => 'user',
                'is_verified' => false,
                'kyc_status' => 'rejected',
                'gold_holdings' => 0,
                'silver_holdings' => 0,
                'wallet_balance' => 0,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => Hash::make('password')])
            );
        }

        // ── KYC Details ──────────────────────────────────────
        $kycData = [
            'rahul@example.com' => ['full_name' => 'Rahul Sharma', 'pan' => 'ABCPK1234A', 'aadhaar' => '123456789012', 'face' => 'approved'],
            'priya@example.com' => ['full_name' => 'Priya Patel', 'pan' => 'BCDPP5678B', 'aadhaar' => '234567890123', 'face' => 'approved'],
            'amit@example.com' => ['full_name' => 'Amit Kumar', 'pan' => 'CDEFG9012C', 'aadhaar' => '345678901234', 'face' => 'pending'],
            'sneha@example.com' => ['full_name' => 'Sneha Reddy', 'pan' => 'DHIJK3456D', 'aadhaar' => '456789012345', 'face' => 'pending'],
            'vikram@example.com' => ['full_name' => 'Vikram Singh', 'pan' => 'ELMNO7890E', 'aadhaar' => '567890123456', 'face' => 'approved'],
            'anita@example.com' => ['full_name' => 'Anita Desai', 'pan' => 'FPQRS1234F', 'aadhaar' => '678901234567', 'face' => 'rejected', 'rejection' => 'Document image unclear'],
        ];

        foreach ($kycData as $email => $kyc) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            KycDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $kyc['full_name'],
                    'pan_number' => $kyc['pan'],
                    'aadhaar_number' => $kyc['aadhaar'],
                    'date_of_birth' => '1990-05-15',
                    'address' => '123 MG Road, Bangalore',
                    'city' => 'Bangalore',
                    'state' => 'Karnataka',
                    'pincode' => '560001',
                    'face_verification_status' => $kyc['face'],
                    'rejection_reason' => $kyc['rejection'] ?? null,
                    'submitted_at' => now()->subDays(rand(1, 30)),
                    'reviewed_at' => in_array($kyc['face'], ['approved', 'rejected']) ? now()->subDays(rand(0, 5)) : null,
                    'reviewed_by' => in_array($kyc['face'], ['approved', 'rejected']) ? $admin?->id : null,
                ]
            );
        }

        // ── Metal Rates (history) ──────────────────────────────
        foreach (['gold' => [7200, 7225, 7250], 'silver' => [83, 84.25, 85.50]] as $metal => $rates) {
            foreach ($rates as $i => $rate) {
                MetalRate::create([
                    'metal_type' => $metal,
                    'rate_per_gram' => $rate,
                    'source' => $i === count($rates) - 1 ? 'live_sync' : 'manual_override',
                    'is_active' => $i === count($rates) - 1,
                    'updated_by' => $admin?->id,
                    'notes' => $i === count($rates) - 1 ? 'Current live rate' : 'Historical rate',
                    'created_at' => now()->subDays(count($rates) - $i),
                ]);
            }
        }

        // ── Investments ────────────────────────────────────────
        $investors = User::where('role', 'investor')->where('is_blocked', false)->get();
        $goldRate = 7250.00;
        $silverRate = 85.50;
        $counter = 1;

        foreach ($investors as $user) {
            for ($d = 0; $d < 5; $d++) {
                $metal = $d % 2 === 0 ? 'gold' : 'silver';
                $rate = $metal === 'gold' ? $goldRate : $silverRate;
                $qty = $metal === 'gold' ? round(rand(5, 50) / 10, 4) : round(rand(50, 500) / 10, 4);
                $amount = round($qty * $rate, 2);
                $gst = round($amount * 0.03, 2);
                $type = $d % 3 === 0 ? 'sell' : 'buy';

                Investment::create([
                    'reference_id' => 'INV-'.str_pad($counter++, 5, '0', STR_PAD_LEFT),
                    'user_id' => $user->id,
                    'metal_type' => $metal,
                    'type' => $type,
                    'quantity_grams' => $qty,
                    'rate_per_gram' => $rate,
                    'amount' => $amount,
                    'gst_amount' => $gst,
                    'total_amount' => round($amount + $gst, 2),
                    'status' => ['completed', 'completed', 'completed', 'pending', 'cancelled'][$d % 5],
                    'created_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }

        // ── Investment Goals ───────────────────────────────────
        $goals = [
            ['title' => 'Wedding Gold', 'metal' => 'gold', 'target' => 50, 'current' => 12.5],
            ['title' => 'Festival Silver', 'metal' => 'silver', 'target' => 1000, 'current' => 250],
            ['title' => 'Retirement Gold', 'metal' => 'gold', 'target' => 100, 'current' => 25],
            ['title' => 'Child Education', 'metal' => 'gold', 'target' => 30, 'current' => 8.75],
        ];

        $i = 0;
        foreach ($investors as $user) {
            $g = $goals[$i % count($goals)];
            InvestmentGoal::create([
                'user_id' => $user->id,
                'title' => $g['title'],
                'metal_type' => $g['metal'],
                'target_grams' => $g['target'],
                'current_grams' => $g['current'],
                'target_amount' => $g['target'] * ($g['metal'] === 'gold' ? $goldRate : $silverRate),
                'target_date' => now()->addMonths(rand(6, 24)),
                'status' => ['active', 'active', 'completed'][$i % 3],
            ]);
            $i++;
        }

        // ── Redemptions ────────────────────────────────────────
        $statuses = ['pending', 'approved', 'processing', 'dispatched', 'delivered', 'rejected'];
        foreach ($investors->take(3) as $idx => $user) {
            Redemption::create([
                'reference_id' => 'RED-'.str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'metal_type' => $idx % 2 === 0 ? 'gold' : 'silver',
                'quantity_grams' => $idx % 2 === 0 ? 2.5000 : 100.0000,
                'amount' => $idx % 2 === 0 ? 18125.00 : 8550.00,
                'status' => $statuses[$idx],
                'delivery_address' => '456 Park Street, Mumbai, Maharashtra - 400001',
                'tracking_number' => in_array($statuses[$idx], ['dispatched', 'delivered']) ? 'DTDC'.rand(100000, 999999) : null,
                'courier_name' => in_array($statuses[$idx], ['dispatched', 'delivered']) ? 'DTDC Express' : null,
                'dispatched_at' => in_array($statuses[$idx], ['dispatched', 'delivered']) ? now()->subDays(2) : null,
                'delivered_at' => $statuses[$idx] === 'delivered' ? now()->subDay() : null,
                'processed_by' => $admin?->id,
            ]);
        }

        // ── Wallet Transactions ────────────────────────────────
        foreach ($investors as $user) {
            $balance = (float) $user->wallet_balance;
            WalletTransaction::create([
                'reference_id' => 'WLT-'.strtoupper(substr(md5($user->email.'c'), 0, 8)),
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $balance,
                'balance_after' => $balance,
                'description' => 'Initial wallet top-up',
                'source' => 'admin',
                'created_by' => $admin?->id,
                'created_at' => now()->subDays(30),
            ]);

            WalletTransaction::create([
                'reference_id' => 'WLT-'.strtoupper(substr(md5($user->email.'d'), 0, 8)),
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => 5000.00,
                'balance_after' => max(0, $balance - 5000),
                'description' => 'Gold purchase deduction',
                'source' => 'investment',
                'created_at' => now()->subDays(15),
            ]);
        }

        // ── GST Records (last 7 days) ──────────────────────────
        $gstService = app(GstService::class);
        for ($d = 6; $d >= 0; $d--) {
            $gstService->calculateForDate(Carbon::today()->subDays($d));
        }

        // ── Daily Reports (last 7 days) ────────────────────────
        for ($d = 6; $d >= 0; $d--) {
            $date = Carbon::today()->subDays($d);
            DailyReport::updateOrCreate(
                ['report_date' => $date->toDateString()],
                [
                    'new_users' => rand(1, 5),
                    'active_investors' => $investors->count(),
                    'gold_holdings_total' => User::sum('gold_holdings'),
                    'silver_holdings_total' => User::sum('silver_holdings'),
                    'revenue_total' => Investment::where('status', 'completed')->whereDate('created_at', $date)->sum('total_amount'),
                    'transaction_count' => Investment::whereDate('created_at', $date)->count(),
                    'gst_collected' => Investment::where('status', 'completed')->whereDate('created_at', $date)->sum('gst_amount'),
                ]
            );
        }

        // ── CMS ────────────────────────────────────────────────
        Banner::updateOrCreate(['title' => 'Invest in Digital Gold'], [
            'image' => PlaceholderImage::banner('gold-promo.svg', 'Invest in Digital Gold', '#d4a017'),
            'link' => '/invest/gold',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Banner::updateOrCreate(['title' => 'Silver at Best Rates'], [
            'image' => PlaceholderImage::banner('silver-promo.svg', 'Silver at Best Rates', '#94a3b8'),
            'link' => '/invest/silver',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        Banner::updateOrCreate(['title' => 'Festival Offer'], [
            'image' => PlaceholderImage::banner('festival.svg', 'Festival Offer', '#ea580c'),
            'link' => '/offers',
            'sort_order' => 3,
            'is_active' => false,
        ]);

        Offer::updateOrCreate(['promo_code' => 'GOLD10'], [
            'title' => '10% Off First Gold Purchase', 'description' => 'New user special offer.',
            'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true,
            'starts_at' => now()->subWeek(), 'ends_at' => now()->addMonth(),
        ]);
        Offer::updateOrCreate(['promo_code' => 'SILVER50'], [
            'title' => '₹50 Off Silver', 'description' => 'Flat discount on silver.',
            'discount_type' => 'flat', 'discount_value' => 50, 'is_active' => true,
        ]);

        $faqs = [
            ['vault_security', 'What are the physical storage protocols for Hoxtan Gold?', 'All holdings are stored in allocated, segregated vaults with insured deep-storage protocols and quarterly audits.'],
            ['withdrawals', 'How long do international bullion withdrawals take to process?', 'International bullion withdrawals typically process within 3-5 business days after compliance verification.'],
            ['trading', 'Is there a minimum liquidity requirement for elite trading?', 'Minimum trade sizes apply based on metal type. Gold 999.9 starts at 1.0oz and Silver 999 at 100oz.'],
            ['kyc_account', 'What documentation is required for Corporate KYC?', 'Corporate KYC requires incorporation documents, beneficial ownership declarations, and authorized signatory identification.'],
            ['trading', 'How do I buy digital gold?', 'Complete KYC, add wallet funds, and buy at live rates.'],
            ['trading', 'What is the minimum investment?', 'You can start with as little as ₹100 for digital purchases.'],
            ['withdrawals', 'How do I redeem physical gold?', 'Submit a redemption request from the app; we deliver to your saved address.'],
            ['vault_security', 'Is my gold secure?', 'Yes, all holdings are backed by physical gold stored in insured vaults.'],
            ['trading', 'What are the GST charges?', 'A 3% GST (1.5% CGST + 1.5% SGST) applies on all transactions.'],
        ];
        foreach ($faqs as $i => [$category, $q, $a]) {
            Faq::updateOrCreate(['question' => $q], [
                'answer' => $a,
                'category' => $category,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        StaticPage::updateOrCreate(['slug' => 'terms-and-conditions'], [
            'title' => 'Terms & Conditions',
            'content' => '<h2>Terms of Service</h2><p>By using Gold & Silver platform you agree to our terms...</p>',
            'is_published' => true,
        ]);
        StaticPage::updateOrCreate(['slug' => 'privacy-policy'], [
            'title' => 'Privacy Policy',
            'content' => '<h2>Privacy Policy</h2><p>We respect your privacy and protect your personal data...</p>',
            'is_published' => true,
        ]);
        StaticPage::updateOrCreate(['slug' => 'about-us'], [
            'title' => 'About Us',
            'content' => '<h2>About Gold & Silver</h2><p>India\'s trusted digital precious metals platform.</p>',
            'is_published' => true,
        ]);

        // ── Push Notifications ─────────────────────────────────
        PushNotification::create([
            'title' => 'Gold Price Drop Alert!',
            'body' => 'Gold prices dropped by 2%. Great time to invest!',
            'target' => 'all', 'status' => 'sent', 'sent_at' => now()->subDays(3),
            'created_by' => $admin?->id,
        ]);
        PushNotification::create([
            'title' => 'Complete Your KYC',
            'body' => 'Verify your account to start investing today.',
            'target' => 'investors', 'status' => 'sent', 'sent_at' => now()->subDay(),
            'created_by' => $admin?->id,
        ]);
        PushNotification::create([
            'title' => 'New Festival Offer',
            'body' => 'Get 10% off on your first gold purchase. Use code GOLD10.',
            'target' => 'all', 'status' => 'draft',
            'created_by' => $admin?->id,
        ]);
        PushNotification::create([
            'title' => 'Monthly Portfolio Summary',
            'body' => 'Your monthly investment summary is ready.',
            'target' => 'investors', 'status' => 'scheduled',
            'scheduled_at' => now()->addDays(3),
            'created_by' => $admin?->id,
        ]);
    }
}
