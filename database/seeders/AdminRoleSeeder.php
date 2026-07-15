<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        AdminRole::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to every admin module.',
                'permissions' => AdminPermissions::allGranted(),
                'is_active' => true,
                'is_super_admin' => true,
            ],
        );

        AdminRole::updateOrCreate(
            ['slug' => 'support-staff'],
            [
                'name' => 'Support Staff',
                'description' => 'Sample limited role for user and KYC management.',
                'permissions' => $this->supportPermissions(),
                'is_active' => true,
                'is_super_admin' => false,
            ],
        );
    }

    private function supportPermissions(): array
    {
        $permissions = AdminPermissions::emptyMatrix();

        foreach (['dashboard', 'users', 'kyc', 'redemption_requests', 'delivery_tracking'] as $module) {
            $permissions[$module]['view'] = true;
            $permissions[$module]['edit'] = true;
        }

        $permissions['users']['create'] = true;
        $permissions['kyc']['create'] = true;

        foreach ($this->reportModules() as $module) {
            $permissions[$module]['view'] = true;
            $permissions[$module]['export'] = true;
        }

        $permissions['reports_account_controls']['edit'] = true;
        $permissions['wallet_credit_debit']['view'] = true;
        $permissions['wallet_credit_debit']['edit'] = true;

        $permissions['sig_plans']['view'] = true;
        $permissions['sig_plans']['create'] = true;
        $permissions['sig_plans']['edit'] = true;
        $permissions['sig_plans']['export'] = true;

        foreach (['jewellery_categories', 'jewellery_sub_categories', 'jewellery_sub_sub_categories', 'jewellery_products', 'jewellery_emi_plans', 'jewellery_orders', 'jewellery_emi_refunds'] as $module) {
            $permissions[$module]['view'] = true;
            $permissions[$module]['create'] = true;
            $permissions[$module]['edit'] = true;
            $permissions[$module]['delete'] = true;
        }

        $permissions['jewellery_orders']['create'] = false;
        $permissions['jewellery_orders']['delete'] = false;
        $permissions['jewellery_orders']['export'] = true;

        $permissions['metal_withdrawals']['view'] = true;
        $permissions['metal_withdrawals']['edit'] = true;

        return $permissions;
    }

    /**
     * @return list<string>
     */
    private function reportModules(): array
    {
        return [
            'reports_hub',
            'reports_new_users',
            'reports_active_investors',
            'reports_inactive_users',
            'reports_transactions',
            'reports_client_kyc',
            'reports_kyc_status',
            'reports_kyc_documents',
            'reports_jewellery',
            'reports_jewellery_total',
            'reports_jewellery_inventory',
            'reports_employees',
            'reports_account_controls',
            'reports_account_block',
            'reports_wallet_restrictions',
            'reports_wallet_credit',
            'reports_offers_goals',
            'reports_sig_periodic',
            'reports_holdings',
            'reports_buy_metal',
            'reports_sell_withdraw',
            'reports_old_gold',
            'reports_all_purchases',
            'reports_gst_file',
        ];
    }
}
