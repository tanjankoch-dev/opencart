<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Language;

class LanguageTest extends TestCase
{
    private Language $language;

    protected function setUp(): void
    {
        $this->language = new Language('en-gb');
    }

    // --- Construction ---

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Language::class, $this->language);
    }

    // --- get / set ---

    public function testGetReturnsKeyWhenNotSet(): void
    {
        $this->assertSame('missing_key', $this->language->get('missing_key'));
    }

    public function testSetAndGet(): void
    {
        $this->language->set('heading_title', 'Welcome');
        $this->assertSame('Welcome', $this->language->get('heading_title'));
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $this->language->set('key', 'first');
        $this->language->set('key', 'second');
        $this->assertSame('second', $this->language->get('key'));
    }

    // --- all ---

    public function testAllReturnsEmptyByDefault(): void
    {
        $this->assertSame([], $this->language->all());
    }

    public function testAllReturnsAllSetKeys(): void
    {
        $this->language->set('a', '1');
        $this->language->set('b', '2');
        $all = $this->language->all();
        $this->assertArrayHasKey('a', $all);
        $this->assertArrayHasKey('b', $all);
        $this->assertCount(2, $all);
    }

    public function testAllWithPrefixFiltersKeys(): void
    {
        $this->language->set('text_hello', 'Hello');
        $this->language->set('text_bye', 'Bye');
        $this->language->set('button_save', 'Save');

        $filtered = $this->language->all('text');
        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey('hello', $filtered);
        $this->assertArrayHasKey('bye', $filtered);
        $this->assertArrayNotHasKey('button_save', $filtered);
    }

    public function testAllWithNonMatchingPrefixReturnsEmpty(): void
    {
        $this->language->set('text_hello', 'Hello');
        $this->assertSame([], $this->language->all('nomatch'));
    }

    // --- clear ---

    public function testClearRemovesAllData(): void
    {
        $this->language->set('a', '1');
        $this->language->clear();
        $this->assertSame([], $this->language->all());
        $this->assertSame('a', $this->language->get('a'));
    }

    // --- load from file ---

    public function testLoadFromFile(): void
    {
        $dir = sys_get_temp_dir() . '/oc_lang_test_' . getmypid() . '/';
        @mkdir($dir . 'en-gb', 0777, true);

        file_put_contents($dir . 'en-gb/common.php', "<?php\n\$_['heading'] = 'Test';");

        $this->language->addPath($dir);
        $result = $this->language->load('common');

        $this->assertArrayHasKey('heading', $result);
        $this->assertSame('Test', $this->language->get('heading'));

        // Cleanup
        @unlink($dir . 'en-gb/common.php');
        @rmdir($dir . 'en-gb');
        @rmdir($dir);
    }

    public function testLoadWithPrefixRenamesKeys(): void
    {
        $dir = sys_get_temp_dir() . '/oc_lang_pfx_' . getmypid() . '/';
        @mkdir($dir . 'en-gb', 0777, true);

        file_put_contents($dir . 'en-gb/mod.php', "<?php\n\$_['name'] = 'Mod';");

        $this->language->addPath($dir);
        $this->language->load('mod', 'ext');

        $this->assertSame('Mod', $this->language->get('ext_name'));

        @unlink($dir . 'en-gb/mod.php');
        @rmdir($dir . 'en-gb');
        @rmdir($dir);
    }

    public function testLoadCachesFileContents(): void
    {
        $dir = sys_get_temp_dir() . '/oc_lang_cache_' . getmypid() . '/';
        @mkdir($dir . 'en-gb', 0777, true);

        file_put_contents($dir . 'en-gb/cached.php', "<?php\n\$_['val'] = 'A';");

        $this->language->addPath($dir);
        $this->language->load('cached');

        // Overwrite file; second load should return cached value.
        file_put_contents($dir . 'en-gb/cached.php', "<?php\n\$_['val'] = 'B';");
        $this->language->load('cached');
        $this->assertSame('A', $this->language->get('val'));

        @unlink($dir . 'en-gb/cached.php');
        @rmdir($dir . 'en-gb');
        @rmdir($dir);
    }

    public function testLoadNonexistentFileReturnsCurrentData(): void
    {
        $dir = sys_get_temp_dir() . '/oc_lang_nofile_' . getmypid() . '/';
        @mkdir($dir, 0777, true);

        $this->language->addPath($dir);
        $this->language->set('existing', 'yes');
        $result = $this->language->load('nonexistent');

        $this->assertArrayHasKey('existing', $result);

        @rmdir($dir);
    }
}
