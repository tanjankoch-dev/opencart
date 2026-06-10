<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Document;

class DocumentTest extends TestCase
{
    private Document $document;

    protected function setUp(): void
    {
        $this->document = new Document();
    }

    // ---- Title ----

    public function testGetTitleReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->document->getTitle());
    }

    public function testSetTitleAndGetTitle(): void
    {
        $this->document->setTitle('My Store');
        $this->assertSame('My Store', $this->document->getTitle());
    }

    public function testSetTitleOverwritesPreviousValue(): void
    {
        $this->document->setTitle('First');
        $this->document->setTitle('Second');
        $this->assertSame('Second', $this->document->getTitle());
    }

    // ---- Description ----

    public function testGetDescriptionReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->document->getDescription());
    }

    public function testSetDescriptionAndGetDescription(): void
    {
        $this->document->setDescription('A great store');
        $this->assertSame('A great store', $this->document->getDescription());
    }

    public function testSetDescriptionOverwritesPreviousValue(): void
    {
        $this->document->setDescription('Old');
        $this->document->setDescription('New');
        $this->assertSame('New', $this->document->getDescription());
    }

    // ---- Keywords ----

    public function testGetKeywordsReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->document->getKeywords());
    }

    public function testSetKeywordsAndGetKeywords(): void
    {
        $this->document->setKeywords('shop, ecommerce, opencart');
        $this->assertSame('shop, ecommerce, opencart', $this->document->getKeywords());
    }

    public function testSetKeywordsOverwritesPreviousValue(): void
    {
        $this->document->setKeywords('old');
        $this->document->setKeywords('new');
        $this->assertSame('new', $this->document->getKeywords());
    }

    // ---- Links ----

    public function testGetLinksReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->document->getLinks());
    }

    public function testAddLinkAndGetLinks(): void
    {
        $this->document->addLink('https://example.com', 'canonical');

        $expected = [
            'https://example.com' => [
                'href' => 'https://example.com',
                'rel'  => 'canonical',
            ],
        ];

        $this->assertSame($expected, $this->document->getLinks());
    }

    public function testAddMultipleLinks(): void
    {
        $this->document->addLink('https://example.com', 'canonical');
        $this->document->addLink('https://example.com/feed', 'alternate');

        $links = $this->document->getLinks();
        $this->assertCount(2, $links);
        $this->assertArrayHasKey('https://example.com', $links);
        $this->assertArrayHasKey('https://example.com/feed', $links);
    }

    public function testAddLinkWithSameHrefOverwritesPrevious(): void
    {
        $this->document->addLink('https://example.com', 'canonical');
        $this->document->addLink('https://example.com', 'alternate');

        $links = $this->document->getLinks();
        $this->assertCount(1, $links);
        $this->assertSame('alternate', $links['https://example.com']['rel']);
    }

    // ---- Styles ----

    public function testGetStylesReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->document->getStyles());
    }

    public function testAddStyleWithDefaults(): void
    {
        $this->document->addStyle('style.css');

        $expected = [
            'style.css' => [
                'href'  => 'style.css',
                'rel'   => 'stylesheet',
                'media' => 'screen',
            ],
        ];

        $this->assertSame($expected, $this->document->getStyles());
    }

    public function testAddStyleWithCustomRelAndMedia(): void
    {
        $this->document->addStyle('print.css', 'stylesheet', 'print');

        $styles = $this->document->getStyles();
        $this->assertSame('print', $styles['print.css']['media']);
        $this->assertSame('stylesheet', $styles['print.css']['rel']);
    }

    public function testAddMultipleStyles(): void
    {
        $this->document->addStyle('a.css');
        $this->document->addStyle('b.css');

        $this->assertCount(2, $this->document->getStyles());
    }

    public function testAddStyleWithSameHrefOverwritesPrevious(): void
    {
        $this->document->addStyle('style.css', 'stylesheet', 'screen');
        $this->document->addStyle('style.css', 'stylesheet', 'print');

        $styles = $this->document->getStyles();
        $this->assertCount(1, $styles);
        $this->assertSame('print', $styles['style.css']['media']);
    }

    // ---- Scripts ----

    public function testGetScriptsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->document->getScripts());
    }

    public function testAddScriptAndGetScripts(): void
    {
        $this->document->addScript('app.js');

        $expected = [
            'app.js' => ['href' => 'app.js'],
        ];

        $this->assertSame($expected, $this->document->getScripts());
    }

    public function testAddMultipleScripts(): void
    {
        $this->document->addScript('a.js');
        $this->document->addScript('b.js');

        $this->assertCount(2, $this->document->getScripts());
    }

    public function testAddScriptWithSameHrefOverwritesPrevious(): void
    {
        $this->document->addScript('app.js');
        $this->document->addScript('app.js');

        $this->assertCount(1, $this->document->getScripts());
    }

    // ---- Metas ----

    public function testGetMetasReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->document->getMetas());
    }

    public function testAddMetaAndGetMetas(): void
    {
        $this->document->addMeta(['name' => 'description', 'content' => 'Page desc']);

        $metas = $this->document->getMetas();
        $this->assertCount(1, $metas);
        $this->assertSame('description', $metas[0]['name']);
        $this->assertSame('Page desc', $metas[0]['content']);
    }

    public function testAddMultipleMetas(): void
    {
        $this->document->addMeta(['name' => 'description', 'content' => 'Desc']);
        $this->document->addMeta(['property' => 'og:title', 'content' => 'Title']);
        $this->document->addMeta(['name' => 'theme-color', 'content' => '#000', 'media' => '(prefers-color-scheme: dark)']);

        $metas = $this->document->getMetas();
        $this->assertCount(3, $metas);
        $this->assertSame('og:title', $metas[1]['property']);
        $this->assertSame('#000', $metas[2]['content']);
        $this->assertArrayHasKey('media', $metas[2]);
    }
}
