<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ContactsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'shop.name' => (string) config('app.name', 'dormed-shop'),
            'shop.email' => 'mail@dormed.de',
            'shop.phone' => '02301188600',
            'shop.fax' => '02301188620',
            'shop.bank_account_holder' => 'Dormed medizinische Systeme GmbH',
            'shop.bank_iban' => 'DE 0000000...',
            'shop.bank_bic' => 'FANTASIEBIC',
            'shop.bank_name' => 'Sparkasse UnnaKamen',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
