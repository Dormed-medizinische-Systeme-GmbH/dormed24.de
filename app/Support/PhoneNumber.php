<?php

namespace App\Support;

/**
 * Deutsche Telefonnummer aus Vorwahl und Rufnummer. Getrennt gespeichert,
 * weil sich aus reinen Ziffern nicht ablesen lässt, wo die Vorwahl endet.
 */
final readonly class PhoneNumber
{
    public function __construct(
        public string $areaCode,
        public string $number,
    ) {}

    /**
     * @param  array{area_code: string, number: string}  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self($config['area_code'], $config['number']);
    }

    /**
     * Nur Ziffern, z. B. "02301188600".
     */
    public function digits(): string
    {
        return $this->onlyDigits($this->areaCode).$this->onlyDigits($this->number);
    }

    /**
     * Anzeigeformat "02301 / 188-600": Bindestrich vor den letzten drei
     * Ziffern, bei kurzen Rufnummern ohne.
     */
    public function display(): string
    {
        $number = $this->onlyDigits($this->number);

        if (strlen($number) > 3) {
            $number = substr($number, 0, -3).'-'.substr($number, -3);
        }

        return $this->onlyDigits($this->areaCode).' / '.$number;
    }

    /**
     * tel:-Link im internationalen Format, z. B. "tel:+492301188600".
     */
    public function href(): string
    {
        return 'tel:+49'.ltrim($this->digits(), '0');
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
