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

    // --- addHeader() / getHeaders() ---

    public function testGetHeadersReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->response->getHeaders());
    }

    public function testAddHeaderStoresHeader(): void
    {
        $this->response->addHeader('Content-Type: text/html');
        $this->assertSame(['Content-Type: text/html'], $this->response->getHeaders());
    }

    public function testAddMultipleHeaders(): void
    {
        $this->response->addHeader('Content-Type: text/html');
        $this->response->addHeader('X-Custom: value');
        $this->assertCount(2, $this->response->getHeaders());
    }

    // --- setOutput() / getOutput() ---

    public function testGetOutputReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->response->getOutput());
    }

    public function testSetOutputStoresOutput(): void
    {
        $this->response->setOutput('<html></html>');
        $this->assertSame('<html></html>', $this->response->getOutput());
    }

    public function testSetOutputOverwritesPrevious(): void
    {
        $this->response->setOutput('first');
        $this->response->setOutput('second');
        $this->assertSame('second', $this->response->getOutput());
    }

    // --- setCompression() ---

    public function testSetCompressionDoesNotAffectOutputDirectly(): void
    {
        $this->response->setCompression(5);
        $this->response->setOutput('test');
        $this->assertSame('test', $this->response->getOutput());
    }

    // --- output() ---

    public function testOutputWithNoContentDoesNotEcho(): void
    {
        ob_start();
        $this->response->output();
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    public function testOutputEchosContent(): void
    {
        $this->response->setOutput('<p>Hello</p>');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        $this->assertSame('<p>Hello</p>', $output);
    }

    public function testOutputWithCompressionAndGzipEncoding(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';

        $this->response->setCompression(5);
        $this->response->setOutput('Hello World');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        // Output should be gzip-compressed (binary data, not the original string)
        $this->assertNotSame('Hello World', $output);

        // The Content-Encoding header should have been added
        $headers = $this->response->getHeaders();
        $this->assertContains('Content-Encoding: gzip', $headers);

        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testOutputWithCompressionAndXGzipEncoding(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'x-gzip';

        $this->response->setCompression(5);
        $this->response->setOutput('Test content');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        $headers = $this->response->getHeaders();
        $this->assertContains('Content-Encoding: x-gzip', $headers);

        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testOutputWithCompressionButNoAcceptEncodingReturnsRaw(): void
    {
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);

        $this->response->setCompression(5);
        $this->response->setOutput('raw content');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        $this->assertSame('raw content', $output);
    }

    public function testOutputWithInvalidCompressionLevelReturnsRaw(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $this->response->setCompression(-2); // Invalid: < -1
        $this->response->setOutput('raw content');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        $this->assertSame('raw content', $output);

        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testOutputWithHighInvalidCompressionLevelReturnsRaw(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $this->response->setCompression(10); // Invalid: > 9
        $this->response->setOutput('raw content');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        $this->assertSame('raw content', $output);

        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testCompressReturnsRawWhenZlibOutputCompressionEnabled(): void
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $oldValue = ini_get('zlib.output_compression');
        ini_set('zlib.output_compression', '1');

        $this->response->setCompression(5);
        $this->response->setOutput('test zlib');

        ob_start();
        $this->response->output();
        $output = ob_get_clean();

        $this->assertSame('test zlib', $output);

        ini_set('zlib.output_compression', $oldValue ?: '0');
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
}
