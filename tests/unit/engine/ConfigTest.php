<?php
declare(strict_types=1);

namespace Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Opencart\System\Engine\Config;

class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
    }

    public function testGetReturnsEmptyStringForUnknownKey(): void
    {
        $this->assertSame('', $this->config->get('nonexistent'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $this->config->set('foo', 'bar');
        $this->assertSame('bar', $this->config->get('foo'));
    }

    public function testSetOverwritesExistingKey(): void
    {
        $this->config->set('key', 'old');
        $this->config->set('key', 'new');
        $this->assertSame('new', $this->config->get('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->config->has('missing'));
    }

    public function testHasReturnsTrueForSetKey(): void
    {
        $this->config->set('present', 'value');
        $this->assertTrue($this->config->has('present'));
    }

    public function testAddPathSetsDefaultDirectory(): void
    {
        $dir = __DIR__ . '/fixtures/config/';
        $this->config->addPath($dir);

        // Verify load works with the set directory
        $result = $this->config->load('nonexistent_file_xyz');
        $this->assertSame([], $result);
    }

    public function testAddPathWithNamespace(): void
    {
        $dir = '/tmp/test-config/';
        $this->config->addPath('/tmp/default-dir/');
        $this->config->addPath('mynamespace', $dir);

        $result = $this->config->load('mynamespace/missing');
        $this->assertSame([], $result);
    }

    public function testLoadReturnsEmptyArrayWhenFileNotFound(): void
    {
        $this->config->addPath('/tmp/nonexistent-dir/');
        $result = $this->config->load('no_such_file');
        $this->assertSame([], $result);
    }

    public function testLoadMergesDataFromFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/oc_config_test_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);

        file_put_contents($tmpDir . 'test.php', '<?php $_["site_name"] = "OpenCart"; $_["version"] = "4.0";');

        $this->config->addPath($tmpDir);
        $result = $this->config->load('test');

        $this->assertSame('OpenCart', $this->config->get('site_name'));
        $this->assertSame('4.0', $this->config->get('version'));
        $this->assertArrayHasKey('site_name', $result);
        $this->assertArrayHasKey('version', $result);

        // Cleanup
        unlink($tmpDir . 'test.php');
        rmdir($tmpDir);
    }

    public function testLoadPreservesExistingData(): void
    {
        $tmpDir = sys_get_temp_dir() . '/oc_config_test_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);

        file_put_contents($tmpDir . 'extra.php', '<?php $_["new_key"] = "new_val";');

        $this->config->addPath($tmpDir);
        $this->config->set('existing', 'value');

        $this->config->load('extra');

        $this->assertSame('value', $this->config->get('existing'));
        $this->assertSame('new_val', $this->config->get('new_key'));

        unlink($tmpDir . 'extra.php');
        rmdir($tmpDir);
    }

    public function testLoadWithNamespacedPath(): void
    {
        $tmpDir = sys_get_temp_dir() . '/oc_config_ns_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);

        file_put_contents($tmpDir . 'settings.php', '<?php $_["ns_key"] = "ns_val";');

        $this->config->addPath('/tmp/default-dir/');
        $this->config->addPath('ext', $tmpDir);

        $result = $this->config->load('ext/settings');

        $this->assertSame('ns_val', $this->config->get('ns_key'));

        unlink($tmpDir . 'settings.php');
        rmdir($tmpDir);
    }

    public function testSetAcceptsMixedValueTypes(): void
    {
        $this->config->set('int_val', 42);
        $this->assertSame(42, $this->config->get('int_val'));

        $this->config->set('bool_val', true);
        $this->assertTrue($this->config->get('bool_val'));

        $this->config->set('array_val', ['a', 'b']);
        $this->assertSame(['a', 'b'], $this->config->get('array_val'));
    }
}
