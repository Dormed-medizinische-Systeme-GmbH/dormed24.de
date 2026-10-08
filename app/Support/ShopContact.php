<?php

namespace App\Support;

/**
 * Kontaktdaten und Bankverbindung des Shops aus config/shop.php. Einzige
 * Stelle, an der Shop, Mails und Admin diese Werte lesen; im Admin sind
 * sie nicht änderbar (.docs/adr/0004-keine-konfiguration-im-admin.md).
 */
final class ShopContact
{
    /**
     * Shopname, wie er in Mails und im Seitentitel erscheint (APP_NAME).
     */
    public function name(): string
    {
        return (string) config('app.name');
    }

    /**
     * Adresse für Kundenkontakt und Betreiber-Benachrichtigungen; null,
     * wenn keine hinterlegt ist.
     */
    public function email(): ?string
    {
        $email = (string) config('shop.contact.email', '');

        return $email !== '' ? $email : null;
    }

    public function phone(): PhoneNumber
    {
        /** @var array{area_code: string, number: string} $phone */
        $phone = config('shop.contact.phone');

        return PhoneNumber::fromConfig($phone);
    }

    public function fax(): PhoneNumber
    {
        /** @var array{area_code: string, number: string} $fax */
        $fax = config('shop.contact.fax');

        return PhoneNumber::fromConfig($fax);
    }

    /**
     * Bankverbindung für Rechnungskäufe. Fehlende Werte sind leere Strings.
     *
     * @return array{account_holder: string, iban: string, bic: string, name: string}
     */
    public function bank(): array
    {
        return [
            'account_holder' => (string) config('shop.bank.account_holder', ''),
            'iban' => (string) config('shop.bank.iban', ''),
            'bic' => (string) config('shop.bank.bic', ''),
            'name' => (string) config('shop.bank.name', ''),
        ];
    }

    /**
     * Kontakt fürs Frontend (geteilte Prop "contact").
     *
     * @return array{email: string, phone: string, fax: string, phone_href: string, fax_href: string, phone_display: string, fax_display: string}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email() ?? '',
            'phone' => $this->phone()->digits(),
            'fax' => $this->fax()->digits(),
            'phone_href' => $this->phone()->href(),
            'fax_href' => $this->fax()->href(),
            'phone_display' => $this->phone()->display(),
            'fax_display' => $this->fax()->display(),
        ];
    }
}
