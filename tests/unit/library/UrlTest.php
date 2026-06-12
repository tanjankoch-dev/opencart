<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Url;

class UrlTest extends TestCase
{
    private Url $url;

    protected function setUp(): void
    {
        $this->url = new Url('http://localhost/');
    }

    // --- Construction ---

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Url::class, $this->url);
    }

    // --- link() ---

    public function testLinkGeneratesRouteUrl(): void
    {
        $result = $this->url->link('common/home');
        $this->assertSame('http://localhost/index.php?route=common/home', $result);
    }

    public function testLinkWithStringArgs(): void
    {
        $result = $this->url->link('product/product', 'product_id=1');
        $this->assertStringContainsString('product_id=1', $result);
    }

    public function testLinkWithArrayArgs(): void
    {
        $result = $this->url->link('product/product', ['product_id' => 1, 'page' => 2]);
        $this->assertStringContainsString('product_id=1', $result);
        $this->assertStringContainsString('page=2', $result);
    }

    public function testLinkEncodesAmpersandsByDefault(): void
    {
        $result = $this->url->link('product/product', 'a=1&b=2');
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringNotContainsString('&&', $result);
    }

    public function testLinkWithJsFlagSkipsAmpersandEncoding(): void
    {
        $result = $this->url->link('product/product', 'a=1&b=2', true);
        $this->assertStringNotContainsString('&amp;', $result);
    }

    public function testLinkWithEmptyArgs(): void
    {
        $result = $this->url->link('common/home', '');
        $this->assertSame('http://localhost/index.php?route=common/home', $result);
    }

    // --- addRewrite ---

    public function testAddRewriteAppliesRewriter(): void
    {
        $rewriter = new class {
            public function rewrite(string $url): string
            {
                return str_replace('index.php?route=common/home', 'home', $url);
            }
        };

        $this->url->addRewrite($rewriter);
        $result = $this->url->link('common/home', '', true);
        $this->assertSame('http://localhost/home', $result);
    }

    public function testAddRewriteIgnoresObjectWithoutRewriteMethod(): void
    {
        $this->url->addRewrite(new \stdClass());
        // Should still produce a normal URL without errors.
        $result = $this->url->link('common/home');
        $this->assertStringContainsString('route=common/home', $result);
    }

    public function testMultipleRewritersAreChained(): void
    {
        $r1 = new class {
            public function rewrite(string $url): string
            {
                return str_replace('index.php?route=', '', $url);
            }
        };
        $r2 = new class {
            public function rewrite(string $url): string
            {
                return str_replace('common/home', 'start', $url);
            }
        };

        $this->url->addRewrite($r1);
        $this->url->addRewrite($r2);

        $result = $this->url->link('common/home', '', true);
        $this->assertSame('http://localhost/start', $result);
    }

    // --- Edge cases ---

    public function testLinkWithTrailingAmpersandInArgs(): void
    {
        $result = $this->url->link('route', '&foo=bar&', true);
        // trim should handle leading/trailing &
        $this->assertStringContainsString('foo=bar', $result);
    }

    public function testConstructWithHttps(): void
    {
        $url = new Url('https://shop.example.com/');
        $result = $url->link('checkout/cart', '', true);
        $this->assertStringStartsWith('https://shop.example.com/', $result);
    }
}
