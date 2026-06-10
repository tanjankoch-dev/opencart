<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use Opencart\System\Library\Image;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase {
	private string $tempDir;

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/oc_image_test_' . uniqid();
		mkdir($this->tempDir, 0755, true);
	}

	protected function tearDown(): void {
		$files = glob($this->tempDir . '/*');

		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		if (is_dir($this->tempDir)) {
			rmdir($this->tempDir);
		}
	}

	private function createPng(int $width = 100, int $height = 80): string {
		$path = $this->tempDir . '/test.png';
		$img = imagecreatetruecolor($width, $height);
		imagepng($img, $path);
		imagedestroy($img);

		return $path;
	}

	private function createJpeg(int $width = 100, int $height = 80): string {
		$path = $this->tempDir . '/test.jpg';
		$img = imagecreatetruecolor($width, $height);
		imagejpeg($img, $path);
		imagedestroy($img);

		return $path;
	}

	public function testThrowsExceptionForMissingFile(): void {
		$this->expectException(\Exception::class);
		new Image('/nonexistent/path/image.png');
	}

	public function testGetFileReturnsPath(): void {
		$path = $this->createPng();
		$image = new Image($path);
		static::assertSame($path, $image->getFile());
	}

	public function testGetWidthAndHeight(): void {
		$path = $this->createPng(200, 150);
		$image = new Image($path);
		static::assertSame(200, $image->getWidth());
		static::assertSame(150, $image->getHeight());
	}

	public function testGetMimeReturnsPngMime(): void {
		$path = $this->createPng();
		$image = new Image($path);
		static::assertSame('image/png', $image->getMime());
	}

	public function testGetMimeReturnsJpegMime(): void {
		$path = $this->createJpeg();
		$image = new Image($path);
		static::assertSame('image/jpeg', $image->getMime());
	}

	public function testGetImageReturnsResource(): void {
		$path = $this->createPng();
		$image = new Image($path);
		$resource = $image->getImage();
		static::assertTrue(is_object($resource) || is_resource($resource));
	}

	public function testSaveAsJpeg(): void {
		$path = $this->createPng(50, 50);
		$image = new Image($path);

		$outputPath = $this->tempDir . '/output.jpg';
		$image->save($outputPath, 90);

		static::assertFileExists($outputPath);
		$info = getimagesize($outputPath);
		static::assertSame('image/jpeg', $info['mime']);
	}

	public function testSaveAsPng(): void {
		$path = $this->createPng(50, 50);
		$image = new Image($path);

		$outputPath = $this->tempDir . '/output.png';
		$image->save($outputPath);

		static::assertFileExists($outputPath);
		$info = getimagesize($outputPath);
		static::assertSame('image/png', $info['mime']);
	}

	public function testResizeUpdatesDimensions(): void {
		$path = $this->createPng(200, 200);
		$image = new Image($path);
		$image->resize(100, 100);
		static::assertSame(100, $image->getWidth());
		static::assertSame(100, $image->getHeight());
	}

	public function testCropUpdatesDimensions(): void {
		$path = $this->createPng(200, 200);
		$image = new Image($path);
		$image->crop(10, 10, 110, 110);
		static::assertSame(100, $image->getWidth());
		static::assertSame(100, $image->getHeight());
	}

	public function testRotateUpdatesDimensions(): void {
		$path = $this->createJpeg(200, 100);
		$image = new Image($path);
		$image->rotate(90);
		// After 90° rotation, width/height may swap (approximately).
		static::assertSame(100, $image->getWidth());
		static::assertSame(200, $image->getHeight());
	}

	public function testGetBitsReturnsString(): void {
		$path = $this->createPng();
		$image = new Image($path);
		static::assertIsString($image->getBits());
	}
}
