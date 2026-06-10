<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Log;

class LogTest extends TestCase
{
    private string $logDir;
    private string $originalDirLogs;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/oc_log_test_' . getmypid() . '/';
        @mkdir($this->logDir, 0777, true);

        // Store original and redefine DIR_LOGS for test isolation
        if (defined('DIR_LOGS')) {
            $this->originalDirLogs = DIR_LOGS;
        }
    }

    protected function tearDown(): void
    {
        // Clean up any files created
        $files = glob($this->logDir . '*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->logDir);
    }

    private function createLog(string $filename): Log
    {
        // We need to use a reflection trick since DIR_LOGS is already defined
        // and Log uses it. We'll work with the defined DIR_LOGS path.
        return new Log($filename);
    }

    public function testWriteCreatesFileAndAppendsMessage(): void
    {
        $filename = 'test_' . getmypid() . '.log';
        $log = $this->createLog($filename);

        $log->write('Hello World');

        $path = DIR_LOGS . $filename;
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('Hello World', $content);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $content);

        @unlink($path);
    }

    public function testWriteAppendsMultipleLines(): void
    {
        $filename = 'test_multi_' . getmypid() . '.log';
        $log = $this->createLog($filename);

        $log->write('Line 1');
        $log->write('Line 2');

        $path = DIR_LOGS . $filename;
        $content = file_get_contents($path);

        $this->assertStringContainsString('Line 1', $content);
        $this->assertStringContainsString('Line 2', $content);

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines);

        @unlink($path);
    }

    public function testWriteHandlesArrayMessage(): void
    {
        $filename = 'test_array_' . getmypid() . '.log';
        $log = $this->createLog($filename);

        $log->write(['key' => 'value']);

        $path = DIR_LOGS . $filename;
        $content = file_get_contents($path);

        $this->assertStringContainsString('key', $content);
        $this->assertStringContainsString('value', $content);

        @unlink($path);
    }

    public function testConstructorCreatesFileIfNotExists(): void
    {
        $filename = 'test_create_' . getmypid() . '.log';
        $path = DIR_LOGS . $filename;

        // Ensure file doesn't exist
        @unlink($path);
        $this->assertFileDoesNotExist($path);

        $log = $this->createLog($filename);
        $this->assertFileExists($path);

        @unlink($path);
    }
}
