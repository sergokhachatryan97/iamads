<?php

namespace Database\Seeders;

use App\Models\MtprotoTelegramAccount;
use Illuminate\Database\Seeder;

class MtprotoTelegramAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            'acc_001',
            'acc_002',
            'acc_003',
            'acc_004',
            'acc_005',
            'acc_006',
            'acc_007',
            'acc_008',
            'acc_009',
            'acc_010',
            'acc_011',
            'acc_012',
            'acc_013',
            'acc_014',
            'acc_015',
        ];

        foreach ($accounts as $name) {
            MtprotoTelegramAccount::firstOrCreate(
                ['session_name' => $name],
                [
                    'is_active' => true,
                    'is_probe' => true,
                    'is_inspect' => true,
                    'subscription_count' => 0,
                    'proxy_type' => 'http',
                    'proxy_host' => 'proxy.geonode.io',
                    'proxy_port' => 9000,
                    'proxy_pass' => '094d7fe8-5ecd-4a90-aade-d622945d25c9',
                    'proxy_user' => 'geonode_4tdeD16jpy-type-residential',
                    'force_proxy' => 1,
                ]
            );
        }
    }
}
