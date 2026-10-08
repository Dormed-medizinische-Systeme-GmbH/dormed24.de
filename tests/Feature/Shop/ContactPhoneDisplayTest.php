<?php

namespace Tests\Feature\Shop;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Footer und Hero zeigen Telefon und Fax als "02301 / 188-600". Gepflegt
 * werden Vorwahl und Rufnummer getrennt in config/shop.php.
 */
class ContactPhoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_contact_is_shared_from_the_config(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contact.email', 'mail@dormed.de')
                ->where('contact.phone', '02301188600')
                ->where('contact.phone_display', '02301 / 188-600')
                ->where('contact.fax_display', '02301 / 188-620')
                ->where('contact.phone_href', 'tel:+492301188600')
                ->where('contact.fax_href', 'tel:+492301188620'));
    }

    public function test_changed_config_reaches_the_page(): void
    {
        config(['shop.contact.phone' => ['area_code' => '0231', 'number' => '9876543']]);

        $this->get(route('home'))
            ->assertInertia(fn ($page) => $page
                ->where('contact.phone_display', '0231 / 9876-543')
                ->where('contact.phone_href', 'tel:+492319876543'));
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'Shop' => ['02301', '188600', '02301 / 188-600', 'tel:+492301188600'],
            'Großstadt' => ['030', '1234567', '030 / 1234-567', 'tel:+49301234567'],
            'mit Leerzeichen gepflegt' => ['02301', '188 620', '02301 / 188-620', 'tel:+492301188620'],
            'kurze Rufnummer' => ['02301', '123', '02301 / 123', 'tel:+492301123'],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_phone_number_formats(string $areaCode, string $number, string $display, string $href): void
    {
        $phone = new PhoneNumber($areaCode, $number);

        $this->assertSame($display, $phone->display());
        $this->assertSame($href, $phone->href());
    }
}
