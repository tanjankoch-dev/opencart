<?php
declare(strict_types=1);

namespace Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Opencart\System\Engine\Registry;

class RegistryTest extends TestCase
{
    private Registry $registry;

    protected function setUp(): void
    {
        $this->registry = new Registry();
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->registry->get('missing'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $obj = new \stdClass();
        $this->registry->set('foo', $obj);
        $this->assertSame($obj, $this->registry->get('foo'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->registry->has('missing'));
    }

    public function testHasReturnsTrueAfterSet(): void
    {
        $this->registry->set('bar', new \stdClass());
        $this->assertTrue($this->registry->has('bar'));
    }

    public function testUnsetRemovesKey(): void
    {
        $this->registry->set('baz', new \stdClass());
        $this->registry->unset('baz');
        $this->assertFalse($this->registry->has('baz'));
        $this->assertNull($this->registry->get('baz'));
    }

    public function testMagicSetAndGet(): void
    {
        $obj = new \stdClass();
        $this->registry->foo = $obj;
        $this->assertSame($obj, $this->registry->foo);
    }

    public function testMagicIsset(): void
    {
        $this->assertFalse(isset($this->registry->qux));
        $this->registry->set('qux', new \stdClass());
        $this->assertTrue(isset($this->registry->qux));
    }
}
