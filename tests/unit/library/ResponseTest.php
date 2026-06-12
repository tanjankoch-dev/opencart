<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Response;

class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    // --- Construction ---

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Response::class, $this->response);
    }

    // --- Headers ---

    public function testGetHeadersDefaultsToEmpty(): void
    {
        $this->assertSame([], $this->response->getHeaders());
    }

    public function testAddHeaderStoresHeader(): void
    {
        $this->response->addHeader('Content-Type: text/html');
        $headers = $this->response->getHeaders();
        $this->assertCount(1, $headers);
        $this->assertSame('Content-Type: text/html', $headers[0]);
    }

    public function testAddMultipleHeaders(): void
    {
        $this->response->addHeader('Content-Type: text/html');
        $this->response->addHeader('X-Custom: value');
        $this->assertCount(2, $this->response->getHeaders());
    }

    // --- Output ---

    public function testGetOutputDefaultsToEmpty(): void
    {
        $this->assertSame('', $this->response->getOutput());
    }

    public function testSetAndGetOutput(): void
    {
        $this->response->setOutput('<h1>Hello</h1>');
        $this->assertSame('<h1>Hello</h1>', $this->response->getOutput());
    }

    public function testSetOutputOverwritesPrevious(): void
    {
        $this->response->setOutput('first');
        $this->response->setOutput('second');
        $this->assertSame('second', $this->response->getOutput());
    }

    // --- Compression ---

    public function testSetCompressionDoesNotThrow(): void
    {
        $this->response->setCompression(5);
        $this->assertTrue(true, 'setCompression executed without error');
    }

    public function testSetCompressionToZero(): void
    {
        $this->response->setCompression(0);
        $this->assertTrue(true);
    }

    // --- output() method (echo) ---

    public function testOutputMethodEchoesContent(): void
    {
        $this->response->setOutput('Hello World');
        ob_start();
        $this->response->output();
        $captured = ob_get_clean();
        $this->assertSame('Hello World', $captured);
    }

    public function testOutputMethodWithEmptyOutputProducesNothing(): void
    {
        ob_start();
        $this->response->output();
        $captured = ob_get_clean();
        $this->assertSame('', $captured);
    }
}
