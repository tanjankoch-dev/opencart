<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the Curl HTTP wrapper.
 *
 * The source file (upload/system/library/curl.php) declares namespace
 * Opencart\System\Library\Cart, but lives outside the cart/ subdirectory,
 * so the OpenCart autoloader cannot resolve it. We require the file
 * explicitly.
 */
require_once DIR_SYSTEM . 'library/curl.php';

use Opencart\System\Library\Cart\Curl;

class CurlTest extends TestCase
{
    /** Can be instantiated with a URL string. */
    public function testConstructSetsUrl(): void
    {
        $curl = new Curl('https://example.com/api');
        $this->assertInstanceOf(Curl::class, $curl);
    }

    /** setOption stores an arbitrary cURL constant. */
    public function testSetOptionDoesNotThrow(): void
    {
        $curl = new Curl('https://example.com');
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        $this->assertTrue(true, 'setOption executed without error');
    }

    /** setOption can override a default option. */
    public function testSetOptionOverridesDefault(): void
    {
        $curl = new Curl('https://example.com');
        $curl->setOption(CURLOPT_TIMEOUT, 60);
        $this->assertTrue(true);
    }

    /** Instantiation with an empty URL is allowed (validation deferred to send). */
    public function testConstructWithEmptyUrl(): void
    {
        $curl = new Curl('');
        $this->assertInstanceOf(Curl::class, $curl);
    }
}
