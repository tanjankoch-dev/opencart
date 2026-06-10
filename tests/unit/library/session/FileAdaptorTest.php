<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Session;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Session\File;
use Tests\Support\RegistryBuilder;

class FileAdaptorTest extends TestCase
{
    private string $sessionDir;
    private \Opencart\System\Engine\Registry $registry;

    protected function setUp(): void
    {
        $this->sessionDir = sys_get_temp_dir() . '/oc_session_test_' . getmypid() . '/';

        if (!is_dir($this->sessionDir)) {
            mkdir($this->sessionDir, 0777, true);
        }

        if (!defined('DIR_SESSION')) {
            define('DIR_SESSION', $this->sessionDir);
        }

        $config = new class {
            private array $data = [
                'session_expire'      => 86400,
                'session_divisor'     => 1,
                'session_probability' => 1,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $this->registry = (new RegistryBuilder())->with('config', $config)->build();
    }

    protected function tearDown(): void
    {
        $files = glob($this->sessionDir . 'sess_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testReadReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $adaptor = new File($this->registry);
        $this->assertSame([], $adaptor->read('nonexistent1234567890ab'));
    }

    public function testReadReturnsDataFromFile(): void
    {
        $data = ['user_id' => 5, 'lang' => 'en'];
        file_put_contents($this->sessionDir . 'sess_testsession12345678ab', json_encode($data));

        $adaptor = new File($this->registry);
        $result = $adaptor->read('testsession12345678ab');

        $this->assertSame(5, $result['user_id']);
        $this->assertSame('en', $result['lang']);
    }

    public function testWriteCreatesFileWithData(): void
    {
        $adaptor = new File($this->registry);
        $result = $adaptor->write('writesession123456abcd', ['cart' => [1, 2]]);

        $this->assertTrue($result);
        $this->assertFileExists($this->sessionDir . 'sess_writesession123456abcd');

        $content = json_decode(file_get_contents($this->sessionDir . 'sess_writesession123456abcd'), true);
        $this->assertSame([1, 2], $content['cart']);
    }

    public function testDestroyRemovesFile(): void
    {
        $file = $this->sessionDir . 'sess_destroysession1234abcd';
        file_put_contents($file, json_encode(['x' => 1]));
        $this->assertFileExists($file);

        $adaptor = new File($this->registry);
        $adaptor->destroy('destroysession1234abcd');

        $this->assertFileDoesNotExist($file);
    }

    public function testDestroyDoesNothingWhenFileDoesNotExist(): void
    {
        $adaptor = new File($this->registry);
        $adaptor->destroy('nofile_session_abcdef22');
        $this->assertTrue(true);
    }

    public function testGcRemovesExpiredFiles(): void
    {
        // Create an expired file (old modification time)
        $expiredFile = $this->sessionDir . 'sess_expired1234567890ab';
        file_put_contents($expiredFile, json_encode(['old' => true]));
        touch($expiredFile, time() - 100000);

        // Create a fresh file
        $freshFile = $this->sessionDir . 'sess_fresh12345678901234';
        file_put_contents($freshFile, json_encode(['new' => true]));

        $adaptor = new File($this->registry);
        $adaptor->gc();

        $this->assertFileDoesNotExist($expiredFile);
        $this->assertFileExists($freshFile);
    }
}
