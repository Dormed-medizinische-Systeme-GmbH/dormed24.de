<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class MailSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'mail.smtp_host' => env('MAIL_HOST'),
            'mail.smtp_port' => env('MAIL_PORT'),
            'mail.smtp_user' => env('MAIL_USERNAME'),
            'mail.smtp_password' => env('MAIL_PASSWORD'),
        ];

        foreach ($settings as $key => $value) {
            if ($value !== null && $value !== '') {
                Setting::set($key, (string) $value);
            }
        }
    }
}
