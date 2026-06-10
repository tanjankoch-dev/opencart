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
        // Set up superglobals before constructing Request
        $_GET = ['search' => '<b>test</b>', 'page' => '1'];
        $_POST = ['name' => '<script>alert("xss")</script>'];
        $_COOKIE = ['session_id' => 'abc123'];
        $_FILES = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1'];

        $this->request = new Request();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    // --- Constructor / clean ---

    public function testConstructorSanitizesGetData(): void
    {
        $this->assertSame('&lt;b&gt;test&lt;/b&gt;', $this->request->get['search']);
        $this->assertSame('1', $this->request->get['page']);
    }

    public function testConstructorSanitizesPostData(): void
    {
        $this->assertStringNotContainsString('<script>', $this->request->post['name']);
    }

    public function testConstructorSanitizesCookieData(): void
    {
        $this->assertSame('abc123', $this->request->cookie['session_id']);
    }

    public function testConstructorSanitizesServerData(): void
    {
        $this->assertSame('GET', $this->request->server['REQUEST_METHOD']);
    }

    // --- clean() ---

    public function testCleanHandlesNestedArrays(): void
    {
        $data = ['outer' => ['inner' => '<b>bold</b>']];
        $cleaned = $this->request->clean($data);
        $this->assertSame('&lt;b&gt;bold&lt;/b&gt;', $cleaned['outer']['inner']);
    }

    public function testCleanTrimsWhitespace(): void
    {
        $this->assertSame('trimmed', $this->request->clean('  trimmed  '));
    }

    public function testCleanHandlesScalarString(): void
    {
        $this->assertSame('hello', $this->request->clean('hello'));
    }

    public function testCleanSanitizesArrayKeys(): void
    {
        $data = ['<key>' => 'value'];
        $cleaned = $this->request->clean($data);
        $this->assertArrayHasKey('&lt;key&gt;', $cleaned);
    }

    // --- get() method ---

    public function testGetMethodReturnsValueForExistingKey(): void
    {
        $this->assertSame('1', $this->request->get('page'));
    }

    public function testGetMethodReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->request->get('nonexistent'));
    }

    public function testGetMethodCastsToString(): void
    {
        $this->assertSame('1', $this->request->get('page', 'string'));
    }

    public function testGetMethodCastsToInt(): void
    {
        $this->assertSame(1, $this->request->get('page', 'int'));
    }

    public function testGetMethodCastsToFloat(): void
    {
        $this->assertSame(1.0, $this->request->get('page', 'float'));
    }

    public function testGetMethodCastsToBool(): void
    {
        $this->assertSame(true, $this->request->get('page', 'bool'));
    }

    public function testGetMethodCastsToArray(): void
    {
        $result = $this->request->get('page', 'array');
        $this->assertIsArray($result);
    }

    public function testGetMethodDefaultTypeReturnsRawValue(): void
    {
        $this->assertSame('1', $this->request->get('page'));
    }

    public function testGetMethodCastsNullToString(): void
    {
        $this->assertSame('', $this->request->get('nonexistent', 'string'));
    }

    public function testGetMethodCastsNullToInt(): void
    {
        $this->assertSame(0, $this->request->get('nonexistent', 'int'));
    }

    public function testGetMethodCastsNullToFloat(): void
    {
        $this->assertSame(0.0, $this->request->get('nonexistent', 'float'));
    }

    public function testGetMethodCastsNullToBool(): void
    {
        $this->assertSame(false, $this->request->get('nonexistent', 'bool'));
    }

    // --- post() method ---

    public function testPostMethodReturnsValueForExistingKey(): void
    {
        $this->assertIsString($this->request->post('name'));
    }

    public function testPostMethodReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->request->post('nonexistent'));
    }

    public function testPostMethodCastsToString(): void
    {
        $result = $this->request->post('name', 'string');
        $this->assertIsString($result);
    }

    public function testPostMethodCastsToInt(): void
    {
        $this->assertSame(0, $this->request->post('name', 'int'));
    }

    public function testPostMethodCastsToFloat(): void
    {
        $this->assertSame(0.0, $this->request->post('name', 'float'));
    }

    public function testPostMethodCastsToBool(): void
    {
        $this->assertSame(true, $this->request->post('name', 'bool'));
    }

    public function testPostMethodCastsToArray(): void
    {
        $result = $this->request->post('name', 'array');
        $this->assertIsArray($result);
    }

    public function testPostMethodDefaultTypeReturnsRawValue(): void
    {
        $result = $this->request->post('name');
        $this->assertNotNull($result);
    }

    public function testPostMethodCastsNullToString(): void
    {
        $this->assertSame('', $this->request->post('nonexistent', 'string'));
    }

    public function testPostMethodCastsNullToInt(): void
    {
        $this->assertSame(0, $this->request->post('nonexistent', 'int'));
    }

    public function testPostMethodCastsNullToFloat(): void
    {
        $this->assertSame(0.0, $this->request->post('nonexistent', 'float'));
    }

    public function testPostMethodCastsNullToBool(): void
    {
        $this->assertSame(false, $this->request->post('nonexistent', 'bool'));
    }
}
