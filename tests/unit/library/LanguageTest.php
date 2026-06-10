<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Language;

class LanguageTest extends TestCase
{
    private Language $language;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->language = new Language('en-gb');
        $this->tmpDir = sys_get_temp_dir() . '/oc_lang_test_' . uniqid() . '/';
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . $item;
            is_dir($path) ? $this->removeDir($path . '/') : unlink($path);
        }
        rmdir($dir);
    }

    // --- get() ---

    public function testGetReturnsKeyWhenNotSet(): void
    {
        $this->assertSame('missing_key', $this->language->get('missing_key'));
    }

    public function testGetReturnsValueWhenSet(): void
    {
        $this->language->set('greeting', 'Hello');
        $this->assertSame('Hello', $this->language->get('greeting'));
    }

    // --- set() ---

    public function testSetOverwritesExistingValue(): void
    {
        $this->language->set('key', 'old');
        $this->language->set('key', 'new');
        $this->assertSame('new', $this->language->get('key'));
    }

    // --- all() ---

    public function testAllReturnsAllDataWhenNoPrefix(): void
    {
        $this->language->set('foo', 'bar');
        $this->language->set('baz', 'qux');

        $data = $this->language->all();
        $this->assertSame('bar', $data['foo']);
        $this->assertSame('qux', $data['baz']);
    }

    public function testAllFiltersByPrefix(): void
    {
        $this->language->set('button_save', 'Save');
        $this->language->set('button_cancel', 'Cancel');
        $this->language->set('heading_title', 'Title');

        $result = $this->language->all('button');
        $this->assertSame('Save', $result['save']);
        $this->assertSame('Cancel', $result['cancel']);
        $this->assertArrayNotHasKey('heading_title', $result);
    }

    public function testAllWithNonMatchingPrefixReturnsEmpty(): void
    {
        $this->language->set('foo', 'bar');
        $result = $this->language->all('zzz');
        $this->assertSame([], $result);
    }

    // --- clear() ---

    public function testClearRemovesAllData(): void
    {
        $this->language->set('key', 'value');
        $this->language->clear();
        $this->assertSame('key', $this->language->get('key'));
        $this->assertSame([], $this->language->all());
    }

    // --- addPath() ---

    public function testAddPathSetsDefaultDirectory(): void
    {
        $this->language->addPath($this->tmpDir);

        // Verify by loading a file that exists in this directory
        mkdir($this->tmpDir . 'en-gb', 0755, true);
        file_put_contents($this->tmpDir . 'en-gb/common.php', '<?php $_["site"] = "OpenCart";');

        $this->language->load('common');
        $this->assertSame('OpenCart', $this->language->get('site'));
    }

    public function testAddPathWithNamespace(): void
    {
        $this->language->addPath($this->tmpDir);

        $nsDir = sys_get_temp_dir() . '/oc_lang_ns_' . uniqid() . '/';
        mkdir($nsDir . 'en-gb', 0755, true);
        file_put_contents($nsDir . 'en-gb/settings.php', '<?php $_["ns_val"] = "namespaced";');

        $this->language->addPath('extension', $nsDir);

        $this->language->load('extension/settings');
        $this->assertSame('namespaced', $this->language->get('ns_val'));

        $this->removeDir($nsDir);
    }

    // --- load() ---

    public function testLoadReturnsDataFromFile(): void
    {
        mkdir($this->tmpDir . 'en-gb', 0755, true);
        file_put_contents($this->tmpDir . 'en-gb/test.php', '<?php $_["loaded"] = "yes";');

        $this->language->addPath($this->tmpDir);
        $result = $this->language->load('test');

        $this->assertSame('yes', $result['loaded']);
        $this->assertSame('yes', $this->language->get('loaded'));
    }

    public function testLoadWithCustomCode(): void
    {
        mkdir($this->tmpDir . 'fr-fr', 0755, true);
        file_put_contents($this->tmpDir . 'fr-fr/test.php', '<?php $_["bonjour"] = "Bonjour";');

        $this->language->addPath($this->tmpDir);
        $result = $this->language->load('test', '', 'fr-fr');

        $this->assertSame('Bonjour', $result['bonjour']);
    }

    public function testLoadWithPrefixRenamesKeys(): void
    {
        mkdir($this->tmpDir . 'en-gb', 0755, true);
        file_put_contents($this->tmpDir . 'en-gb/buttons.php', '<?php $_["save"] = "Save"; $_["cancel"] = "Cancel";');

        $this->language->addPath($this->tmpDir);
        $result = $this->language->load('buttons', 'btn');

        $this->assertSame('Save', $result['btn_save']);
        $this->assertSame('Cancel', $result['btn_cancel']);
    }

    public function testLoadCachesFileContents(): void
    {
        mkdir($this->tmpDir . 'en-gb', 0755, true);
        file_put_contents($this->tmpDir . 'en-gb/cached.php', '<?php $_["val"] = "first";');

        $this->language->addPath($this->tmpDir);

        $this->language->load('cached');
        $this->assertSame('first', $this->language->get('val'));

        // Modify the file, but cache should return old value
        file_put_contents($this->tmpDir . 'en-gb/cached.php', '<?php $_["val"] = "second";');

        $this->language->clear();
        $this->language->load('cached');
        $this->assertSame('first', $this->language->get('val'));
    }

    public function testLoadMissingFileDoesNotError(): void
    {
        $this->language->addPath($this->tmpDir);
        $result = $this->language->load('nonexistent');
        // Should still return data array (possibly empty or with previously set values)
        $this->assertIsArray($result);
    }

    public function testLoadMergesWithExistingData(): void
    {
        mkdir($this->tmpDir . 'en-gb', 0755, true);
        file_put_contents($this->tmpDir . 'en-gb/extra.php', '<?php $_["new_key"] = "new_val";');

        $this->language->addPath($this->tmpDir);
        $this->language->set('existing', 'value');

        $this->language->load('extra');

        $this->assertSame('value', $this->language->get('existing'));
        $this->assertSame('new_val', $this->language->get('new_key'));
    }

    public function testLoadWithMultiPartPathResolvesNamespace(): void
    {
        $nsDir = sys_get_temp_dir() . '/oc_lang_mp_' . uniqid() . '/';
        mkdir($nsDir . 'en-gb', 0755, true);
        file_put_contents($nsDir . 'en-gb/sub.php', '<?php $_["deep"] = "value";');

        $this->language->addPath($this->tmpDir);
        $this->language->addPath('ns', $nsDir);

        $this->language->load('ns/sub');
        $this->assertSame('value', $this->language->get('deep'));

        $this->removeDir($nsDir);
    }
}
