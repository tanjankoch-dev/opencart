<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Log;

/**
 * Tests for the Log library.
 *
 * DIR_LOGS is defined by tests/bootstrap.php and points to
 * upload/system/storage/logs/. We use a unique temp filename
 * per test run to avoid collisions and clean up after ourselves.
 */
class LogTest extends TestCase
{
    private string $filename;
    private string $filepath;

    protected function setUp(): void
    {
        $this->filename = 'phpunit_test_' . getmypid() . '.log';
        $this->filepath = DIR_LOGS . $this->filename;

        // Ensure clean state
        if (is_file($this->filepath)) {
            unlink($this->filepath);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->filepath)) {
            unlink($this->filepath);
        }
    }

    public function testConstructCreatesLogFile(): void
    {
        new Log($this->filename);
        $this->assertFileExists($this->filepath);
    }

    public function testConstructDoesNotTruncateExistingFile(): void
    {
        file_put_contents($this->filepath, 'existing content');
        new Log($this->filename);
        $this->assertStringContainsString('existing content', file_get_contents($this->filepath));
    }

    public function testWriteAppendsMessage(): void
    {
        $log = new Log($this->filename);
        $log->write('Test message');

        $content = file_get_contents($this->filepath);
        $this->assertStringContainsString('Test message', $content);
    }

    public function testWriteIncludesTimestamp(): void
    {
        $log = new Log($this->filename);
        $log->write('timestamped');

        $content = file_get_contents($this->filepath);
        // Timestamp format: Y-m-d H:i:s
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $content);
    }

    public function testMultipleWritesAppend(): void
    {
        $log = new Log($this->filename);
        $log->write('first');
        $log->write('second');

        $content = file_get_contents($this->filepath);
        $this->assertStringContainsString('first', $content);
        $this->assertStringContainsString('second', $content);
    }

    public function testWriteWithArrayMessage(): void
    {
        $log = new Log($this->filename);
        $log->write(['key' => 'value']);

        $content = file_get_contents($this->filepath);
        // print_r of an array contains the key.
        $this->assertStringContainsString('key', $content);
        $this->assertStringContainsString('value', $content);
    }

    public function testWriteWithIntegerMessage(): void
    {
        $log = new Log($this->filename);
        $log->write(42);

        $content = file_get_contents($this->filepath);
        $this->assertStringContainsString('42', $content);
    }

    public function testWriteAppendsNewline(): void
    {
        $log = new Log($this->filename);
        $log->write('line');

        $content = file_get_contents($this->filepath);
        $this->assertStringEndsWith("\n", $content);
    }
}
