<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Url;

class UrlTest extends TestCase
{
    /** Constructor parses and stores the base URL. */
    public function testConstructorStoresUrl(): void
    {
        $url = new Url('https://www.example.com/store/');
        $this->assertSame(
            'https://www.example.com/store/index.php?route=common/home',
            $url->link('common/home', '', true)
        );
    }

    /** Constructor parses URL with port number. */
    public function testConstructorWithPort(): void
    {
        $url = new Url('http://localhost:8080/');
        $this->assertSame(
            'http://localhost:8080/index.php?route=test/route',
            $url->link('test/route', '', true)
        );
    }

    /** Constructor handles URL with query string and fragment. */
    public function testConstructorWithQueryAndFragment(): void
    {
        $url = new Url('https://example.com/store/?lang=en#top');
        // The base URL is stored as-is; link appends to it
        $link = $url->link('common/home', '', true);
        $this->assertStringContainsString('route=common/home', $link);
    }

    // --- link() method ---

    /** link() with route only (no args). */
    public function testLinkWithRouteOnly(): void
    {
        $url = new Url('http://shop.test/');
        $this->assertSame(
            'http://shop.test/index.php?route=product/product',
            $url->link('product/product', '', true)
        );
    }

    /** link() with string args. */
    public function testLinkWithStringArgs(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', 'product_id=42&lang=en', true);
        $this->assertSame(
            'http://shop.test/index.php?route=product/product&product_id=42&lang=en',
            $link
        );
    }

    /** link() with string args trims leading/trailing ampersands. */
    public function testLinkWithStringArgsTrimAmpersand(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', '&product_id=42&', true);
        $this->assertSame(
            'http://shop.test/index.php?route=product/product&product_id=42',
            $link
        );
    }

    /** link() with array args uses http_build_query. */
    public function testLinkWithArrayArgs(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', ['product_id' => 42, 'lang' => 'en'], true);
        $this->assertSame(
            'http://shop.test/index.php?route=product/product&product_id=42&lang=en',
            $link
        );
    }

    /** link() with empty string args does not add extra ampersand. */
    public function testLinkWithEmptyStringArgs(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', '', true);
        $this->assertStringNotContainsString('&&', $link);
        $this->assertSame(
            'http://shop.test/index.php?route=product/product',
            $link
        );
    }

    /** link() with empty array args does not add extra ampersand. */
    public function testLinkWithEmptyArrayArgs(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', [], true);
        $this->assertSame(
            'http://shop.test/index.php?route=product/product',
            $link
        );
    }

    /** link() with js=false (default) HTML-encodes ampersands. */
    public function testLinkHtmlEncodesAmpersandsWhenJsFalse(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', 'product_id=42&lang=en');
        $this->assertStringContainsString('&amp;', $link);
        $this->assertStringNotContainsString('&lang', $link);
    }

    /** link() with js=true does not HTML-encode ampersands. */
    public function testLinkDoesNotEncodeAmpersandsWhenJsTrue(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', 'product_id=42&lang=en', true);
        $this->assertStringNotContainsString('&amp;', $link);
        $this->assertStringContainsString('&lang=en', $link);
    }

    /** link() replaces %3F with ? in the final URL. */
    public function testLinkReplacesEncodedQuestionMark(): void
    {
        // Use a rewrite that injects %3F into the URL
        $rewriter = new class {
            public function rewrite(string $url): string
            {
                return str_replace('?route=', '%3Froute=', $url);
            }
        };

        $url = new Url('http://shop.test/');
        $url->addRewrite($rewriter);
        $link = $url->link('common/home', '', true);
        $this->assertStringContainsString('?route=', $link);
        $this->assertStringNotContainsString('%3F', $link);
    }

    // --- addRewrite() method ---

    /** addRewrite() registers a rewrite object that transforms URLs. */
    public function testAddRewriteAppliesRewrite(): void
    {
        $rewriter = new class {
            public function rewrite(string $url): string
            {
                return str_replace('index.php?route=common/home', 'home', $url);
            }
        };

        $url = new Url('http://shop.test/');
        $url->addRewrite($rewriter);
        $link = $url->link('common/home', '', true);
        $this->assertSame('http://shop.test/home', $link);
    }

    /** addRewrite() ignores objects without a rewrite method. */
    public function testAddRewriteIgnoresNonCallable(): void
    {
        $notARewriter = new class {
            // No rewrite() method
        };

        $url = new Url('http://shop.test/');
        $url->addRewrite($notARewriter);
        $link = $url->link('common/home', '', true);
        // URL should remain unmodified
        $this->assertSame(
            'http://shop.test/index.php?route=common/home',
            $link
        );
    }

    /** Multiple rewrites are applied in order. */
    public function testMultipleRewritesAppliedInOrder(): void
    {
        $rewriter1 = new class {
            public function rewrite(string $url): string
            {
                return str_replace('index.php?route=', '', $url);
            }
        };
        $rewriter2 = new class {
            public function rewrite(string $url): string
            {
                return str_replace('common/home', 'welcome', $url);
            }
        };

        $url = new Url('http://shop.test/');
        $url->addRewrite($rewriter1);
        $url->addRewrite($rewriter2);
        $link = $url->link('common/home', '', true);
        $this->assertSame('http://shop.test/welcome', $link);
    }

    /** link() default js parameter is false — & is encoded. */
    public function testLinkDefaultJsIsFalse(): void
    {
        $url = new Url('http://shop.test/');
        $link = $url->link('product/product', 'id=1');
        $this->assertSame(
            'http://shop.test/index.php?route=product/product&amp;id=1',
            $link
        );
    }
}
