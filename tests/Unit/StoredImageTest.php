<?php

namespace Tests\Unit;

use App\Support\StoredImage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class StoredImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD is not installed.');
        }
    }

    public function test_large_jpeg_is_resized_to_the_longest_side(): void
    {
        $file = UploadedFile::fake()->image('big.jpg', 3000, 2000);

        StoredImage::shrink($file);

        [$width, $height, $type] = getimagesize($file->getRealPath());
        $this->assertSame([1600, 1067, IMAGETYPE_JPEG], [$width, $height, $type]);
    }

    public function test_png_keeps_its_format_and_small_images_are_untouched(): void
    {
        $logo = UploadedFile::fake()->image('logo.png', 1200, 600);
        StoredImage::shrink($logo, StoredImage::LOGO);
        [$width, $height, $type] = getimagesize($logo->getRealPath());
        $this->assertSame([512, 256, IMAGETYPE_PNG], [$width, $height, $type]);

        $small = UploadedFile::fake()->image('small.jpg', 400, 300);
        $before = md5_file($small->getRealPath());
        StoredImage::shrink($small);
        $this->assertSame($before, md5_file($small->getRealPath()));
    }
}
