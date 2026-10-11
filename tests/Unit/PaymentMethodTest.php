<?php

namespace Tests\Unit;

use App\Support\Orders\PaymentMethod;
use PHPUnit\Framework\TestCase;

class PaymentMethodTest extends TestCase
{
    public function test_pay_pal_funded_methods_are_all_but_invoice(): void
    {
        $this->assertSame(
            [PaymentMethod::Paypal, PaymentMethod::Card, PaymentMethod::Sepa],
            PaymentMethod::payPalFunded(),
        );
    }

    public function test_funding_source_is_the_value_for_pay_pal_methods_and_null_for_invoice(): void
    {
        $this->assertSame('paypal', PaymentMethod::Paypal->fundingSource());
        $this->assertSame('sepa', PaymentMethod::Sepa->fundingSource());
        $this->assertSame('card', PaymentMethod::Card->fundingSource());
        $this->assertNull(PaymentMethod::Invoice->fundingSource());
    }

    public function test_every_method_has_a_german_label(): void
    {
        $this->assertSame('Kauf auf Rechnung', PaymentMethod::Invoice->label());
        $this->assertSame('PayPal', PaymentMethod::Paypal->label());
        $this->assertSame('Lastschrift', PaymentMethod::Sepa->label());
        $this->assertSame('Kreditkarte', PaymentMethod::Card->label());
    }
}
