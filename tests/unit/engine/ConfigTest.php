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

    // --- Construction ---

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Config::class, $this->config);
    }

    // --- get / set ---

    public function testGetReturnsEmptyStringForUnsetKey(): void
    {
        $this->assertSame('', $this->config->get('nonexistent'));
    }

    public function testSetAndGet(): void
    {
        $this->config->set('config_name', 'My Store');
        $this->assertSame('My Store', $this->config->get('config_name'));
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $this->config->set('key', 'first');
        $this->config->set('key', 'second');
        $this->assertSame('second', $this->config->get('key'));
    }

    public function testSetAcceptsMixedTypes(): void
    {
        $this->config->set('int_val', 42);
        $this->assertSame(42, $this->config->get('int_val'));

        $this->config->set('bool_val', true);
        $this->assertTrue($this->config->get('bool_val'));

        $this->config->set('array_val', ['a', 'b']);
        $this->assertSame(['a', 'b'], $this->config->get('array_val'));
    }

    // --- has ---

    public function testHasReturnsFalseForUnsetKey(): void
    {
        $this->assertFalse($this->config->has('nonexistent'));
    }

    public function testHasReturnsTrueForSetKey(): void
    {
        $this->config->set('key', 'value');
        $this->assertTrue($this->config->has('key'));
    }

    // --- addPath / load ---

    public function testLoadFromFile(): void
    {
        $dir = sys_get_temp_dir() . '/oc_config_test_' . getmypid() . '/';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . 'test.php', "<?php\n\$_['site_name'] = 'TestStore';");

        $this->config->addPath($dir);
        $result = $this->config->load('test');

        $this->assertArrayHasKey('site_name', $result);
        $this->assertSame('TestStore', $this->config->get('site_name'));

        @unlink($dir . 'test.php');
        @rmdir($dir);
    }

    public function testLoadMergesWithExistingData(): void
    {
        $dir = sys_get_temp_dir() . '/oc_config_merge_' . getmypid() . '/';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . 'extra.php', "<?php\n\$_['new_key'] = 'new_val';");

        $this->config->addPath($dir);
        $this->config->set('existing', 'keep');
        $result = $this->config->load('extra');

        $this->assertSame('keep', $result['existing']);
        $this->assertSame('new_val', $result['new_key']);

        @unlink($dir . 'extra.php');
        @rmdir($dir);
    }

    public function testLoadNonexistentFileReturnsEmptyArray(): void
    {
        $dir = sys_get_temp_dir() . '/oc_config_nofile_' . getmypid() . '/';
        @mkdir($dir, 0777, true);

        $this->config->addPath($dir);
        $result = $this->config->load('does_not_exist');
        $this->assertSame([], $result);

        @rmdir($dir);
    }

    public function testAddPathWithNamespaceOverridesDirectory(): void
    {
        $dir1 = sys_get_temp_dir() . '/oc_config_ns1_' . getmypid() . '/';
        $dir2 = sys_get_temp_dir() . '/oc_config_ns2_' . getmypid() . '/';
        @mkdir($dir1, 0777, true);
        @mkdir($dir2, 0777, true);

        // The namespace path feature replaces the directory prefix for
        // a filename that starts with the namespace.  load('ns/detail')
        // with addPath('ns', $dir2) resolves to $dir2 . '/detail.php'.
        file_put_contents($dir2 . '/detail.php', "<?php\n\$_['from'] = 'namespace';");

        $this->config->addPath($dir1);
        $this->config->addPath('ns', $dir2);

        $result = $this->config->load('ns/detail');
        $this->assertSame('namespace', $this->config->get('from'));

        @unlink($dir2 . '/detail.php');
        @rmdir($dir1);
        @rmdir($dir2);
    }
}
