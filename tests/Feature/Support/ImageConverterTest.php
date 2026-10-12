<?php

namespace Tests\Feature\Support;

use App\Support\Images\ImageConversionException;
use App\Support\Images\ImageConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MakesImages;
use Tests\TestCase;

class ImageConverterTest extends TestCase
{
    use MakesImages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireImageEncoding();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sourceFormats(): array
    {
        return [
            'jpeg' => ['jpeg'],
            'png' => ['png'],
            'gif' => ['gif'],
            'bmp' => ['bmp'],
            'webp' => ['webp'],
            'avif' => ['avif'],
        ];
    }

    #[DataProvider('sourceFormats')]
    public function test_every_supported_format_becomes_webp_and_avif(string $format): void
    {
        $converted = (new ImageConverter)->convert($this->imageBytes($format, 40, 30));

        $this->assertSame('image/webp', getimagesizefromstring($converted->webp)['mime']);
        $this->assertSame('image/avif', getimagesizefromstring($converted->avif)['mime']);
        $this->assertSame(40, $converted->width);
        $this->assertSame(30, $converted->height);
        $this->assertFalse($converted->wasResized);
    }

    public function test_transparency_is_kept(): void
    {
        $converted = (new ImageConverter)->convert($this->imageBytes('png', 10, 10, transparent: true));

        $webp = imagecreatefromstring($converted->webp);
        $alpha = (imagecolorat($webp, 5, 5) >> 24) & 0x7F;

        $this->assertSame(127, $alpha);
    }

    public function test_large_images_are_scaled_down_keeping_the_aspect_ratio(): void
    {
        $converted = (new ImageConverter)->convert($this->imageBytes('png', 3000, 1500));

        $this->assertSame(ImageConverter::MAX_EDGE, $converted->width);
        $this->assertSame(1200, $converted->height);
        $this->assertTrue($converted->wasResized);
    }

    public function test_small_images_are_not_enlarged(): void
    {
        $converted = (new ImageConverter)->convert($this->imageBytes('png', 100, 50));

        $this->assertSame([100, 50], [$converted->width, $converted->height]);
    }

    public function test_data_that_is_not_an_image_is_rejected(): void
    {
        $this->expectException(ImageConversionException::class);

        (new ImageConverter)->convert('das ist kein Bild');
    }
}
