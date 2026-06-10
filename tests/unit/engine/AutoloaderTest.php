<?php
declare(strict_types=1);

namespace Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Opencart\System\Engine\Autoloader;

class AutoloaderTest extends TestCase
{
    public function testRegisterAddsNamespace(): void
    {
        $autoloader = new Autoloader();
        $autoloader->register('TestNs', '/tmp/nonexistent/');

        // load returns false for unknown class
        $result = $autoloader->load('Completely\\Unknown\\Class');
        $this->assertFalse($result);
    }

    public function testLoadReturnsFalseForUnregisteredNamespace(): void
    {
        $autoloader = new Autoloader();
        $result = $autoloader->load('Unknown\\Namespace\\SomeClass');
        $this->assertFalse($result);
    }

    public function testLoadReturnsTrueForRegisteredNamespaceWithFile(): void
    {
        $dir = sys_get_temp_dir() . '/oc_autoload_test_' . getmypid() . '/';
        @mkdir($dir, 0777, true);

        // Autoloader converts CamelCase -> snake_case for non-psr4
        file_put_contents($dir . 'my_class.php', '<?php namespace AutoloadTestNs; class MyClass {}');

        $autoloader = new Autoloader();
        $autoloader->register('AutoloadTestNs', $dir);
        $result = $autoloader->load('AutoloadTestNs\\MyClass');

        $this->assertTrue($result);
        $this->assertTrue(class_exists('AutoloadTestNs\\MyClass', false));

        @unlink($dir . 'my_class.php');
        @rmdir($dir);
    }

    public function testRegisterMultipleDirectories(): void
    {
        $autoloader = new Autoloader();
        $autoloader->register('MultiNs', '/path/one/');
        $autoloader->register('MultiNs', '/path/two/');

        // Should not throw — just returns true with no matching file
        $result = $autoloader->load('MultiNs\\SomeClass');
        $this->assertTrue($result);
    }

    public function testPsr4Loading(): void
    {
        $dir = sys_get_temp_dir() . '/oc_psr4_test_' . getmypid() . '/';
        @mkdir($dir, 0777, true);

        // PSR-4 preserves case
        file_put_contents($dir . 'CamelCase.php', '<?php namespace Psr4TestNs; class CamelCase {}');

        $autoloader = new Autoloader();
        $autoloader->register('Psr4TestNs', $dir, true);
        $result = $autoloader->load('Psr4TestNs\\CamelCase');

        $this->assertTrue($result);
        $this->assertTrue(class_exists('Psr4TestNs\\CamelCase', false));

        @unlink($dir . 'CamelCase.php');
        @rmdir($dir);
    }
}
