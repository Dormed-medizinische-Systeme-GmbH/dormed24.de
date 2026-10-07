<?php

namespace Tests\Unit;

use App\Support\Address\AddressRules;
use PHPUnit\Framework\TestCase;

class AddressRulesTest extends TestCase
{
    public function test_rules_are_keyed_by_prefix(): void
    {
        $rules = AddressRules::forPrefix('shipping_address');

        $this->assertSame(['required', 'string', 'max:255'], $rules['shipping_address.first_name']);
        $this->assertSame(['nullable', 'string', 'max:255'], $rules['shipping_address.company']);
        $this->assertArrayNotHasKey('first_name', $rules);
    }

    public function test_optional_address_turns_required_into_nullable_and_keeps_the_rest(): void
    {
        $rules = AddressRules::forPrefix('billing_address', required: false);

        $this->assertSame(['nullable', 'string', 'max:255'], $rules['billing_address.first_name']);
        $this->assertSame(['nullable', 'string', 'in:'.AddressRules::COUNTRIES], $rules['billing_address.country']);
        $this->assertNotContains('required', array_merge(...array_values($rules)));
    }

    public function test_country_is_limited_to_the_delivery_countries(): void
    {
        $this->assertSame('DE,AT,CH,NL,BE,LU,FR', AddressRules::COUNTRIES);
        $this->assertContains('in:DE,AT,CH,NL,BE,LU,FR', AddressRules::forPrefix('a')['a.country']);
    }
}
