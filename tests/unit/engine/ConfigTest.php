<?php
declare(strict_types=1);

namespace Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Opencart\System\Engine\Config;

class ConfigTest extends TestCase
{
    public function testGetReturnsEmptyStringForUnknownKey(): void
    {
        $config = new Config();
        $this->assertSame('', $config->get('nonexistent'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $config = new Config();
        $config->set('foo', 'bar');
        $this->assertSame('bar', $config->get('foo'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $config = new Config();
        $config->set('key', 'first');
        $config->set('key', 'second');
        $this->assertSame('second', $config->get('key'));
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $config = new Config();
        $this->assertFalse($config->has('missing'));
    }

    public function testHasReturnsTrueAfterSet(): void
    {
        $config = new Config();
        $config->set('exists', true);
        $this->assertTrue($config->has('exists'));
    }

    public function testSetAcceptsMixedTypes(): void
    {
        $config = new Config();
        $config->set('int', 42);
        $config->set('array', [1, 2, 3]);
        $config->set('bool', false);

        $this->assertSame(42, $config->get('int'));
        $this->assertSame([1, 2, 3], $config->get('array'));
        $this->assertFalse($config->get('bool'));
    }

    public function testAddPathSetsDirectory(): void
    {
        $config = new Config();
        $config->addPath('/tmp/test/');
        // load a non-existent file returns empty array
        $result = $config->load('nonexistent_file_xyz');
        $this->assertSame([], $result);
    }

    public function testAddPathWithNamespace(): void
    {
        $config = new Config();
        $config->addPath('/tmp/base/');
        $config->addPath('custom', '/tmp/custom/');
        $result = $config->load('custom/nofile');
        $this->assertSame([], $result);
    }

    public function testLoadExistingFile(): void
    {
        $dir = sys_get_temp_dir() . '/oc_config_test_' . getmypid() . '/';
        @mkdir($dir, 0777, true);
        file_put_contents($dir . 'test.php', '<?php $_["loaded_key"] = "loaded_value";');

        $config = new Config();
        $config->addPath($dir);
        $result = $config->load('test');

        $this->assertSame('loaded_value', $config->get('loaded_key'));
        $this->assertArrayHasKey('loaded_key', $result);

        @unlink($dir . 'test.php');
        @rmdir($dir);
    }
}
