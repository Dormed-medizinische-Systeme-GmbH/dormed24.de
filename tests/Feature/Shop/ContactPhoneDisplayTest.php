<?php

namespace Tests\Feature\Shop;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Footer und Hero zeigen Telefon und Fax als "02301 / 188-600".
 * Der tel:-Link bleibt davon unberührt.
 */
class ContactPhoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_numbers_are_displayed_in_slash_dash_format(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contact.phone_display', '02301 / 188-600')
                ->where('contact.fax_display', '02301 / 188-620')
                ->where('contact.phone_href', 'tel:+492301188600')
                ->where('contact.fax_href', 'tel:+492301188620'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'Gedankenstrich' => ['02301 – 188600', '02301 / 188-600'],
            'bereits formatiert' => ['02301 / 188-620', '02301 / 188-620'],
            'Leerzeichen' => ['0231 9876543', '0231 / 9876-543'],
            'international' => ['+49 2301 188600', '02301 / 188-600'],
            'international mit (0)' => ['+49 (0)2301 188600', '02301 / 188-600'],
            'mit 0049' => ['0049 2301 188600', '02301 / 188-600'],
            'ohne Trenner bleibt' => ['02301188600', '02301188600'],
            'zu kurz bleibt' => ['02301 12', '02301 12'],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_shop_phone_setting_is_formatted_for_display(string $setting, string $expected): void
    {
        Setting::set('shop.phone', $setting);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contact.phone', $setting)
                ->where('contact.phone_display', $expected));
    }
}
