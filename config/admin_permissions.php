<?php

return [
    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'export' => 'Export',
    ],

    'modules' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'Dashboard',
        ],
        'gst_calculation' => [
            'label' => 'Per Day GST Calculation',
            'group' => 'Finance',
        ],
        'users' => [
            'label' => 'Manage Users',
            'group' => 'User Management',
        ],
        'kyc' => [
            'label' => 'View KYC Details',
            'group' => 'User Management',
        ],
        'refer_and_earn' => [
            'label' => 'Refer & Earn',
            'group' => 'User Management',
        ],
        'gold_live_sync' => [
            'label' => 'Live Gold Rate Sync',
            'group' => 'Gold Rate Management',
        ],
        'gold_manual_override' => [
            'label' => 'Manual Gold Rate Override',
            'group' => 'Gold Rate Management',
        ],
        'gold_rate_history' => [
            'label' => 'Gold Rate History',
            'group' => 'Gold Rate Management',
        ],
        'silver_live_sync' => [
            'label' => 'Live Silver Rate Sync',
            'group' => 'Silver Rate Management',
        ],
        'silver_manual_override' => [
            'label' => 'Manual Silver Rate Override',
            'group' => 'Silver Rate Management',
        ],
        'silver_rate_history' => [
            'label' => 'Silver Rate History',
            'group' => 'Silver Rate Management',
        ],
        'buy_transactions' => [
            'label' => 'Buy Transactions',
            'group' => 'Investment Management',
        ],
        'sell_transactions' => [
            'label' => 'Sell Transactions',
            'group' => 'Investment Management',
        ],
        'investment_goals' => [
            'label' => 'Goal Management',
            'group' => 'Investment Management',
        ],
        'sig_plans' => [
            'label' => 'SIG Management',
            'group' => 'Investment Management',
        ],
        'invoices' => [
            'label' => 'Purchase Invoices',
            'group' => 'Investment Management',
        ],
        'redemption_requests' => [
            'label' => 'Redemption Requests',
            'group' => 'Redemption Management',
        ],
        'delivery_tracking' => [
            'label' => 'Delivery Tracking',
            'group' => 'Redemption Management',
        ],
        'dispatch_management' => [
            'label' => 'Dispatch Management',
            'group' => 'Redemption Management',
        ],
        'wallet_transactions' => [
            'label' => 'Wallet Transactions',
            'group' => 'Wallet Management',
        ],
        'wallet_credit_debit' => [
            'label' => 'Credit / Debit Management',
            'group' => 'Wallet Management',
        ],
        'banners' => [
            'label' => 'Banner Management',
            'group' => 'CMS Management',
        ],
        'jewellery_categories' => [
            'label' => 'Jewellery Categories',
            'group' => 'Jewellery Management',
        ],
        'jewellery_sub_categories' => [
            'label' => 'Jewellery Sub Categories',
            'group' => 'Jewellery Management',
        ],
        'jewellery_products' => [
            'label' => 'Jewellery Products',
            'group' => 'Jewellery Management',
        ],
        'jewellery_emi_plans' => [
            'label' => 'EMI Plans',
            'group' => 'Jewellery Management',
        ],
        'jewellery_orders' => [
            'label' => 'Buy Now Orders',
            'group' => 'Jewellery Management',
        ],
        'offers' => [
            'label' => 'Offers Management',
            'group' => 'CMS Management',
        ],
        'faqs' => [
            'label' => 'FAQ Management',
            'group' => 'CMS Management',
        ],
        'static_pages' => [
            'label' => 'Static Pages',
            'group' => 'CMS Management',
        ],
        'reports' => [
            'label' => 'Legacy Reports',
            'group' => 'Reports',
        ],
        'reports_hub' => [
            'label' => 'Report Center',
            'group' => 'Reports',
        ],
        'reports_new_users' => ['label' => 'New User Reports', 'group' => 'Reports'],
        'reports_active_investors' => ['label' => 'Active Investors', 'group' => 'Reports'],
        'reports_inactive_users' => ['label' => 'Inactive Users', 'group' => 'Reports'],
        'reports_transactions' => ['label' => 'Transaction Breakdown', 'group' => 'Reports'],
        'reports_client_kyc' => ['label' => 'Client KYC Bundle', 'group' => 'Reports'],
        'reports_kyc_status' => ['label' => 'KYC Status', 'group' => 'Reports'],
        'reports_kyc_documents' => ['label' => 'Bulk KYC Documents', 'group' => 'Reports'],
        'reports_jewellery' => ['label' => 'Jewellery Activity', 'group' => 'Reports'],
        'reports_jewellery_total' => ['label' => 'Total Jewellery', 'group' => 'Reports'],
        'reports_jewellery_inventory' => ['label' => 'Jewellery Inventory', 'group' => 'Reports'],
        'reports_employees' => ['label' => 'Employee Referrals', 'group' => 'Reports'],
        'reports_account_controls' => ['label' => 'Account Controls', 'group' => 'Reports'],
        'reports_account_block' => ['label' => 'Full Account Block', 'group' => 'Reports'],
        'reports_wallet_credit' => ['label' => 'Wallet Credit', 'group' => 'Reports'],
        'reports_wallet_restrictions' => ['label' => 'Wallet Restrictions', 'group' => 'Reports'],
        'reports_offers_goals' => ['label' => 'Goals & Offers', 'group' => 'Reports'],
        'reports_sig_periodic' => ['label' => 'SIG Periodic', 'group' => 'Reports'],
        'reports_holdings' => ['label' => 'Holdings', 'group' => 'Reports'],
        'reports_buy_metal' => ['label' => 'Buy Gold/Silver', 'group' => 'Reports'],
        'reports_sell_withdraw' => ['label' => 'Sell & Withdraw', 'group' => 'Reports'],
        'reports_old_gold' => ['label' => 'Old Gold Booking', 'group' => 'Reports'],
        'reports_all_purchases' => ['label' => 'All Purchases', 'group' => 'Reports'],
        'reports_gst_file' => ['label' => 'GST Date Range', 'group' => 'Reports'],
        'push_notifications' => [
            'label' => 'Push Notifications',
            'group' => 'Notification Management',
        ],
        'daily_reports' => [
            'label' => 'Daily Reports',
            'group' => 'Dashboard',
        ],
        'admin_users' => [
            'label' => 'Sub Admin Users',
            'group' => 'System',
        ],
        'admin_roles' => [
            'label' => 'Roles & Permissions',
            'group' => 'System',
        ],
        'settings' => [
            'label' => 'Settings',
            'group' => 'System',
        ],
        'drivers' => [
            'label' => 'Drivers',
            'group' => 'Delivery Management',
        ],
        'blocked_pincodes' => [
            'label' => 'Blocked Pincodes',
            'group' => 'Delivery Management',
        ],
    ],
];
