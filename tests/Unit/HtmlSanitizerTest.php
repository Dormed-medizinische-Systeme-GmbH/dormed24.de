<?php

namespace Tests\Unit;

use App\Support\Newsletter\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private function sanitize(string $html): string
    {
        return (new HtmlSanitizer)->sanitize($html);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dangerousMarkup(): array
    {
        return [
            'script' => ['<p>ok</p><script>alert(1)</script>', '<script'],
            'iframe' => ['<iframe src="https://evil.example"></iframe>', '<iframe'],
            'Formular' => ['<form action="https://evil.example"><input name="pw"></form>', '<form'],
            'Event-Handler' => ['<img src="https://dormed24.de/a.png" onerror="alert(1)">', 'onerror'],
            'javascript-Link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'javascript-Link mit Leerzeichen' => ['<a href=" java script:alert(1)">x</a>', 'script:alert'],
            'data-Bild' => ['<img src="data:image/svg+xml;base64,PHN2Zz4=">', 'data:image'],
            'http-Bild' => ['<img src="http://tracker.example/p.gif">', 'http://tracker'],
            'meta refresh' => ['<html><head><meta http-equiv="refresh" content="0;url=https://evil.example"></head><body></body></html>', 'refresh'],
            'Style mit javascript' => ['<div style="background:url(javascript:alert(1))">x</div>', 'javascript:'],
        ];
    }

    #[DataProvider('dangerousMarkup')]
    public function test_dangerous_markup_is_removed(string $html, string $mustNotContain): void
    {
        $this->assertStringNotContainsString($mustNotContain, $this->sanitize($html));
    }

    public function test_mjml_output_keeps_structure_styles_links_and_outlook_comments(): void
    {
        $html = <<<'HTML'
            <!doctype html>
            <html xmlns="http://www.w3.org/1999/xhtml"><head><title>Herbst</title>
            <style type="text/css">#outlook a { padding:0; } .mj-column-per-100 { width:100% !important; }</style>
            <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
            </head><body style="word-spacing:normal;">
            <div style="background:#ffffff;"><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td align="center">
            <img alt="Logo" src="https://dormed24.de/storage/newsletters/logo.png" width="200" style="border:0;display:block;">
            <a href="https://dormed24.de/products" style="color:#1a6bbf;">Zum Shop</a>
            <a href="mailto:mail@dormed.de">Mail</a> <a href="tel:+492301188600">Anrufen</a>
            <p>Grüße aus Kamen – Ä Ö Ü ß €</p>
            </td></tr></table></div></body></html>
            HTML;

        $result = $this->sanitize($html);

        $this->assertStringStartsWith('<!doctype html>', $result);
        $this->assertStringContainsString('.mj-column-per-100', $result);
        $this->assertStringContainsString('<!--[if mso]>', $result);
        $this->assertStringContainsString('src="https://dormed24.de/storage/newsletters/logo.png"', $result);
        $this->assertStringContainsString('href="https://dormed24.de/products"', $result);
        $this->assertStringContainsString('href="mailto:mail@dormed.de"', $result);
        $this->assertStringContainsString('href="tel:+492301188600"', $result);
        $this->assertStringContainsString('role="presentation"', $result);
        $this->assertStringContainsString('Grüße aus Kamen – Ä Ö Ü ß €', $result);
    }

    /**
     * Echte Ausgabe von mjml-browser aus dem Editor (Startvorlage, eine
     * Überschrift geändert): Bis auf Leerraum zwischen Tags bleibt alles.
     */
    public function test_real_editor_output_loses_nothing_but_whitespace(): void
    {
        $html = (string) file_get_contents(dirname(__DIR__).'/Fixtures/newsletter/mjml-browser-output.html');

        $normalize = fn (string $value): string => (string) preg_replace('/\s+/', '', $value);

        $this->assertSame($normalize($html), $normalize($this->sanitize($html)));
    }

    public function test_http_links_are_kept_but_http_images_are_not(): void
    {
        $result = $this->sanitize('<a href="http://dormed24.test/products">Shop</a><img src="http://dormed24.test/a.png">');

        $this->assertStringContainsString('href="http://dormed24.test/products"', $result);
        $this->assertStringNotContainsString('src="http://', $result);
    }

    public function test_empty_html_stays_empty(): void
    {
        $this->assertSame('', $this->sanitize('   '));
    }
}
