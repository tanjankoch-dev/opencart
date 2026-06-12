<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Request;

class RequestTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        // Request reads from superglobals in the constructor,
        // so we set known values before instantiation.
        $_GET    = ['route' => 'common/home', 'id' => '5'];
        $_POST   = ['username' => 'admin'];
        $_COOKIE = ['session_id' => 'abc123'];
        $_FILES  = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'localhost'];

        $this->request = new Request();
    }

    protected function tearDown(): void
    {
        $_GET = $_POST = $_COOKIE = $_FILES = $_SERVER = [];
    }

    // --- Construction ---

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Request::class, $this->request);
    }

    // --- Public properties are populated ---

    public function testGetPropertyPopulatedFromSuperglobal(): void
    {
        $this->assertArrayHasKey('route', $this->request->get);
    }

    public function testPostPropertyPopulatedFromSuperglobal(): void
    {
        $this->assertArrayHasKey('username', $this->request->post);
    }

    public function testCookiePropertyPopulated(): void
    {
        $this->assertArrayHasKey('session_id', $this->request->cookie);
    }

    public function testServerPropertyPopulated(): void
    {
        $this->assertArrayHasKey('REQUEST_METHOD', $this->request->server);
    }

    // --- clean() sanitisation ---

    public function testCleanEscapesHtml(): void
    {
        $result = $this->request->clean('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testCleanTrimsWhitespace(): void
    {
        $result = $this->request->clean('  hello  ');
        $this->assertSame('hello', $result);
    }

    public function testCleanHandlesNestedArrays(): void
    {
        $data = ['outer' => ['inner' => '<b>bold</b>']];
        $result = $this->request->clean($data);
        $this->assertStringNotContainsString('<b>', $result['outer']['inner']);
    }

    public function testCleanHandlesEmptyString(): void
    {
        $this->assertSame('', $this->request->clean(''));
    }

    public function testCleanHandlesEmptyArray(): void
    {
        $this->assertSame([], $this->request->clean([]));
    }

    // --- get() accessor method ---

    public function testGetMethodReturnsValue(): void
    {
        $this->assertSame('common/home', $this->request->get('route', 'string'));
    }

    public function testGetMethodReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->request->get('nonexistent'));
    }

    public function testGetMethodCastsToInt(): void
    {
        $this->assertSame(5, $this->request->get('id', 'int'));
    }

    public function testGetMethodCastsToFloat(): void
    {
        $this->assertSame(5.0, $this->request->get('id', 'float'));
    }

    public function testGetMethodCastsToBool(): void
    {
        $this->assertSame(true, $this->request->get('id', 'bool'));
    }

    public function testGetMethodCastsToArray(): void
    {
        $result = $this->request->get('route', 'array');
        $this->assertIsArray($result);
    }

    // --- post() accessor method ---

    public function testPostMethodReturnsValue(): void
    {
        $this->assertSame('admin', $this->request->post('username', 'string'));
    }

    public function testPostMethodReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->request->post('nonexistent'));
    }
}
