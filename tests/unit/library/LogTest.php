<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use Opencart\System\Library\Log;
use PHPUnit\Framework\TestCase;

class LogTest extends TestCase {
	private string $testFile;

	protected function setUp(): void {
		$this->testFile = DIR_LOGS . 'phpunit_test_' . uniqid() . '.log';
	}

	protected function tearDown(): void {
		if (is_file($this->testFile)) {
			unlink($this->testFile);
		}
	}

	public function testConstructorCreatesLogFile(): void {
		$filename = basename($this->testFile);
		new Log($filename);

		static::assertFileExists($this->testFile);
	}

	public function testWriteAppendsMessage(): void {
		$filename = basename($this->testFile);
		$log = new Log($filename);
		$log->write('Hello World');

		$contents = file_get_contents($this->testFile);
		static::assertStringContainsString('Hello World', $contents);
	}

	public function testWriteContainsTimestamp(): void {
		$filename = basename($this->testFile);
		$log = new Log($filename);
		$log->write('timestamp check');

		$contents = file_get_contents($this->testFile);
		static::assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $contents);
	}

	public function testMultipleWritesAppend(): void {
		$filename = basename($this->testFile);
		$log = new Log($filename);
		$log->write('first');
		$log->write('second');

		$contents = file_get_contents($this->testFile);
		static::assertStringContainsString('first', $contents);
		static::assertStringContainsString('second', $contents);
	}

	public function testWriteWithArrayMessage(): void {
		$filename = basename($this->testFile);
		$log = new Log($filename);
		$log->write(['key' => 'value']);

		$contents = file_get_contents($this->testFile);
		static::assertStringContainsString('key', $contents);
		static::assertStringContainsString('value', $contents);
	}

	public function testConstructorDoesNotOverwriteExistingFile(): void {
		$filename = basename($this->testFile);
		$log = new Log($filename);
		$log->write('existing content');

		// Re-create Log instance for same file
		$log2 = new Log($filename);
		$log2->write('new content');

		$contents = file_get_contents($this->testFile);
		static::assertStringContainsString('existing content', $contents);
		static::assertStringContainsString('new content', $contents);
	}
}
