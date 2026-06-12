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

    // --- Title ---

    public function testTitleDefaultsToEmpty(): void
    {
        $this->assertSame('', $this->document->getTitle());
    }

    public function testSetAndGetTitle(): void
    {
        $this->document->setTitle('My Store');
        $this->assertSame('My Store', $this->document->getTitle());
    }

    public function testSetTitleOverwritesPrevious(): void
    {
        $this->document->setTitle('First');
        $this->document->setTitle('Second');
        $this->assertSame('Second', $this->document->getTitle());
    }

    // --- Description ---

    public function testDescriptionDefaultsToEmpty(): void
    {
        $this->assertSame('', $this->document->getDescription());
    }

    public function testSetAndGetDescription(): void
    {
        $this->document->setDescription('A great store');
        $this->assertSame('A great store', $this->document->getDescription());
    }

    // --- Keywords ---

    public function testKeywordsDefaultsToEmpty(): void
    {
        $this->assertSame('', $this->document->getKeywords());
    }

    public function testSetAndGetKeywords(): void
    {
        $this->document->setKeywords('shop, ecommerce');
        $this->assertSame('shop, ecommerce', $this->document->getKeywords());
    }

    // --- Links ---

    public function testGetLinksDefaultsToEmpty(): void
    {
        $this->assertSame([], $this->document->getLinks());
    }

    public function testAddLinkStoresHrefAndRel(): void
    {
        $this->document->addLink('https://example.com', 'canonical');
        $links = $this->document->getLinks();

        $this->assertArrayHasKey('https://example.com', $links);
        $this->assertSame('canonical', $links['https://example.com']['rel']);
    }

    public function testAddLinkDeduplicatesByHref(): void
    {
        $this->document->addLink('https://example.com', 'canonical');
        $this->document->addLink('https://example.com', 'alternate');
        $this->assertCount(1, $this->document->getLinks());
        $this->assertSame('alternate', $this->document->getLinks()['https://example.com']['rel']);
    }

    // --- Styles ---

    public function testGetStylesDefaultsToEmpty(): void
    {
        $this->assertSame([], $this->document->getStyles());
    }

    public function testAddStyleWithDefaults(): void
    {
        $this->document->addStyle('style.css');
        $styles = $this->document->getStyles();

        $this->assertArrayHasKey('style.css', $styles);
        $this->assertSame('stylesheet', $styles['style.css']['rel']);
        $this->assertSame('screen', $styles['style.css']['media']);
    }

    public function testAddStyleWithCustomRelAndMedia(): void
    {
        $this->document->addStyle('print.css', 'stylesheet', 'print');
        $styles = $this->document->getStyles();
        $this->assertSame('print', $styles['print.css']['media']);
    }

    // --- Scripts ---

    public function testGetScriptsDefaultsToEmpty(): void
    {
        $this->assertSame([], $this->document->getScripts());
    }

    public function testAddScript(): void
    {
        $this->document->addScript('app.js');
        $scripts = $this->document->getScripts();

        $this->assertArrayHasKey('app.js', $scripts);
        $this->assertSame('app.js', $scripts['app.js']['href']);
    }

    public function testAddScriptDeduplicatesByHref(): void
    {
        $this->document->addScript('app.js');
        $this->document->addScript('app.js');
        $this->assertCount(1, $this->document->getScripts());
    }

    // --- Metas ---

    public function testGetMetasDefaultsToEmpty(): void
    {
        $this->assertSame([], $this->document->getMetas());
    }

    public function testAddMeta(): void
    {
        $this->document->addMeta(['name' => 'description', 'content' => 'Hello']);
        $metas = $this->document->getMetas();

        $this->assertCount(1, $metas);
        $this->assertSame('description', $metas[0]['name']);
    }

    public function testAddMultipleMetas(): void
    {
        $this->document->addMeta(['name' => 'description', 'content' => 'A']);
        $this->document->addMeta(['property' => 'og:title', 'content' => 'B']);
        $this->assertCount(2, $this->document->getMetas());
    }
}
