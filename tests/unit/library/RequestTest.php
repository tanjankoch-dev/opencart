<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Request;

class RequestTest extends TestCase
{
    public function testPropertiesAreArraysAfterConstruction(): void
    {
        $request = new Request();

        $this->assertIsArray($request->get);
        $this->assertIsArray($request->post);
        $this->assertIsArray($request->cookie);
        $this->assertIsArray($request->files);
        $this->assertIsArray($request->server);
    }

    public function testCleanStripsHtmlSpecialChars(): void
    {
        $request = new Request();

        $result = $request->clean('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testCleanTrimsWhitespace(): void
    {
        $request = new Request();
        $result = $request->clean('  hello  ');
        $this->assertSame('hello', $result);
    }

    public function testCleanHandlesNestedArrays(): void
    {
        $request = new Request();

        $data = [
            'key' => '<b>bold</b>',
            'nested' => ['inner' => '<i>italic</i>'],
        ];

        $result = $request->clean($data);

        $this->assertStringNotContainsString('<b>', $result['key']);
        $this->assertStringNotContainsString('<i>', $result['nested']['inner']);
    }

    public function testCleanHandlesEmptyString(): void
    {
        $request = new Request();
        $result = $request->clean('');
        $this->assertSame('', $result);
    }

    public function testCleanHandlesEmptyArray(): void
    {
        $request = new Request();
        $result = $request->clean([]);
        $this->assertSame([], $result);
    }

    public function testGetMethodReturnsNullForMissingKey(): void
    {
        $request = new Request();
        $this->assertNull($request->get('nonexistent'));
    }

    public function testPostMethodReturnsNullForMissingKey(): void
    {
        $request = new Request();
        $this->assertNull($request->post('nonexistent'));
    }

    public function testGetMethodWithTypeCasting(): void
    {
        $request = new Request();
        $this->assertSame('', $request->get('missing', 'string'));
        $this->assertSame(0, $request->get('missing', 'int'));
        $this->assertSame(0.0, $request->get('missing', 'float'));
        $this->assertFalse($request->get('missing', 'bool'));
        $this->assertSame([], $request->get('missing', 'array'));
    }

    public function testPostMethodWithTypeCasting(): void
    {
        $request = new Request();
        $this->assertSame('', $request->post('missing', 'string'));
        $this->assertSame(0, $request->post('missing', 'int'));
        $this->assertSame(0.0, $request->post('missing', 'float'));
        $this->assertFalse($request->post('missing', 'bool'));
        $this->assertSame([], $request->post('missing', 'array'));
    }
}
