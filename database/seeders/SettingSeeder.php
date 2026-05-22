<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Company details
            [
                'group' => 'company',
                'key' => 'company_name',
                'value' => 'POS Retail Store',
                'type' => 'string',
                'label' => 'Company Name',
                'description' => 'Name of the company or outlet.',
            ],
            [
                'group' => 'company',
                'key' => 'company_address',
                'value' => 'Jl. Jenderal Sudirman No. 123, Jakarta',
                'type' => 'string',
                'label' => 'Company Address',
                'description' => 'Address of the company.',
            ],
            [
                'group' => 'company',
                'key' => 'company_phone',
                'value' => '021-5551234',
                'type' => 'string',
                'label' => 'Company Phone',
                'description' => 'Phone number.',
            ],
            [
                'group' => 'company',
                'key' => 'company_email',
                'value' => 'info@posretail.com',
                'type' => 'string',
                'label' => 'Company Email',
                'description' => 'Contact email.',
            ],
            [
                'group' => 'company',
                'key' => 'company_tax_rate',
                'value' => '11.0',
                'type' => 'double',
                'label' => 'Company Tax Rate (%)',
                'description' => 'Default tax rate in percent.',
            ],

            // Region/Region formats
            [
                'group' => 'region',
                'key' => 'default_currency',
                'value' => 'IDR',
                'type' => 'string',
                'label' => 'Default Currency',
                'description' => 'Currency code (e.g. IDR, USD).',
            ],
            [
                'group' => 'region',
                'key' => 'currency_symbol',
                'value' => 'Rp',
                'type' => 'string',
                'label' => 'Currency Symbol',
                'description' => 'Currency symbol (e.g. Rp, $).',
            ],
            [
                'group' => 'region',
                'key' => 'thousand_separator',
                'value' => '.',
                'type' => 'string',
                'label' => 'Thousand Separator',
                'description' => 'Thousand separator character.',
            ],
            [
                'group' => 'region',
                'key' => 'decimal_separator',
                'value' => ',',
                'type' => 'string',
                'label' => 'Decimal Separator',
                'description' => 'Decimal separator character.',
            ],
            [
                'group' => 'region',
                'key' => 'default_language',
                'value' => 'id',
                'type' => 'string',
                'label' => 'Default Language',
                'description' => 'Default application language.',
            ],
            [
                'group' => 'region',
                'key' => 'timezone',
                'value' => 'Asia/Jakarta',
                'type' => 'string',
                'label' => 'Timezone',
                'description' => 'Application timezone.',
            ],
            [
                'group' => 'region',
                'key' => 'date_format',
                'value' => 'Y-m-d',
                'type' => 'string',
                'label' => 'Date Format',
                'description' => 'Application date display format.',
            ],

            // Display and Theme configurations
            [
                'group' => 'theme',
                'key' => 'theme_mode',
                'value' => 'dark',
                'type' => 'string',
                'label' => 'Theme Mode',
                'description' => 'Default theme (light/dark).',
            ],
            [
                'group' => 'theme',
                'key' => 'primary_color',
                'value' => '#6366F1',
                'type' => 'string',
                'label' => 'Primary Color',
                'description' => 'Primary UI theme color.',
            ],
            [
                'group' => 'theme',
                'key' => 'sidebar_color',
                'value' => '#1E1E2F',
                'type' => 'string',
                'label' => 'Sidebar Color',
                'description' => 'Color of the sidebar.',
            ],

            // Loyalty points setup
            [
                'group' => 'loyalty',
                'key' => 'loyalty_spend_per_point',
                'value' => '10000.0',
                'type' => 'double',
                'label' => 'Spend Per Point',
                'description' => 'Spend amount to earn 1 loyalty point.',
            ],
            [
                'group' => 'loyalty',
                'key' => 'loyalty_point_value',
                'value' => '100.0',
                'type' => 'double',
                'label' => 'Point Value',
                'description' => 'Monetary value of 1 loyalty point when redeemed.',
            ],

            // Security/Drawer settings
            [
                'group' => 'security',
                'key' => 'drawer_safety_limit',
                'value' => '5000000.0',
                'type' => 'double',
                'label' => 'Drawer Safety Limit',
                'description' => 'Maximum cash allowed in drawer before warning.',
            ],
            [
                'group' => 'security',
                'key' => 'password_min_length',
                'value' => '8',
                'type' => 'integer',
                'label' => 'Minimum Password Length',
                'description' => 'Minimum length for user passwords.',
            ],

            // Invoice/Receipt texts
            [
                'group' => 'invoice',
                'key' => 'receipt_header',
                'value' => 'Thank you for shopping with us!',
                'type' => 'string',
                'label' => 'Receipt Header',
                'description' => 'Text printed at the top of receipts.',
            ],
            [
                'group' => 'invoice',
                'key' => 'receipt_footer',
                'value' => 'Please keep this receipt for returns within 7 days.',
                'type' => 'string',
                'label' => 'Receipt Footer',
                'description' => 'Text printed at the bottom of receipts.',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
