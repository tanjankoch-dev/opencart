<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Image;

/**
 * Tests for the Image library.
 *
 * A real PNG fixture is created in setUp via GD so every test has
 * a valid image on disk without committing a binary fixture.
 */
class ImageTest extends TestCase
{
    private string $fixtureDir;
    private string $pngFile;
    private string $jpgFile;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('ext-gd is required');
        }

        $this->fixtureDir = sys_get_temp_dir() . '/oc_image_test_' . getmypid();
        @mkdir($this->fixtureDir, 0777, true);

        // Create a 100×80 PNG fixture
        $img = imagecreatetruecolor(100, 80);
        $this->pngFile = $this->fixtureDir . '/test.png';
        imagepng($img, $this->pngFile);
        imagedestroy($img);

        // Create a 60×40 JPEG fixture
        $img2 = imagecreatetruecolor(60, 40);
        $this->jpgFile = $this->fixtureDir . '/test.jpg';
        imagejpeg($img2, $this->jpgFile);
        imagedestroy($img2);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->fixtureDir . '/*') ?: []);
        @rmdir($this->fixtureDir);
    }

    // --- Construction ---

    public function testConstructWithValidPng(): void
    {
        $image = new Image($this->pngFile);
        $this->assertInstanceOf(Image::class, $image);
    }

    public function testConstructThrowsForMissingFile(): void
    {
        $this->expectException(\Exception::class);
        new Image('/nonexistent/image.png');
    }

    // --- Getters ---

    public function testGetFile(): void
    {
        $image = new Image($this->pngFile);
        $this->assertSame($this->pngFile, $image->getFile());
    }

    public function testGetWidthAndHeight(): void
    {
        $image = new Image($this->pngFile);
        $this->assertSame(100, $image->getWidth());
        $this->assertSame(80, $image->getHeight());
    }

    public function testGetMimeForPng(): void
    {
        $image = new Image($this->pngFile);
        $this->assertSame('image/png', $image->getMime());
    }

    public function testGetMimeForJpeg(): void
    {
        $image = new Image($this->jpgFile);
        $this->assertSame('image/jpeg', $image->getMime());
    }

    public function testGetImageReturnsGdResource(): void
    {
        $image = new Image($this->pngFile);
        $gd = $image->getImage();
        $this->assertTrue(is_object($gd) || is_resource($gd));
    }

    // --- Resize ---

    public function testResizeChangesWidthAndHeight(): void
    {
        $image = new Image($this->pngFile);
        $image->resize(50, 40);
        $this->assertSame(50, $image->getWidth());
        $this->assertSame(40, $image->getHeight());
    }

    public function testResizeWithZeroDimensionsThrows(): void
    {
        $image = new Image($this->pngFile);
        $this->expectException(\ValueError::class);
        $image->resize(0, 0);
    }

    // --- Save ---

    public function testSaveCreatesFile(): void
    {
        $outFile = $this->fixtureDir . '/out.png';
        $image = new Image($this->pngFile);
        $image->save($outFile);
        $this->assertFileExists($outFile);
    }

    public function testSaveAsJpeg(): void
    {
        $outFile = $this->fixtureDir . '/out.jpg';
        $image = new Image($this->pngFile);
        $image->save($outFile, 85);
        $this->assertFileExists($outFile);
    }

    // --- Crop ---

    public function testCropAdjustsDimensions(): void
    {
        $image = new Image($this->pngFile);
        $image->crop(10, 10, 60, 50);
        $this->assertSame(50, $image->getWidth());
        $this->assertSame(40, $image->getHeight());
    }

    // --- Rotate ---

    public function testRotateUpdatesDimensions(): void
    {
        $image = new Image($this->pngFile);
        $origWidth = $image->getWidth();
        $origHeight = $image->getHeight();
        $image->rotate(90);
        // After 90° rotation, width and height swap.
        $this->assertSame($origHeight, $image->getWidth());
        $this->assertSame($origWidth, $image->getHeight());
    }
}
