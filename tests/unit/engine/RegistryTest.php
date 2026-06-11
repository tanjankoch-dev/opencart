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

    public function testGetReturnsNullForUnknownKey(): void
    {
        $this->assertNull($this->registry->get('nonexistent'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $obj = new \stdClass();
        $this->registry->set('db', $obj);
        $this->assertSame($obj, $this->registry->get('db'));
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $first = new \stdClass();
        $second = new \stdClass();
        $this->registry->set('key', $first);
        $this->registry->set('key', $second);
        $this->assertSame($second, $this->registry->get('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->registry->has('missing'));
    }

    public function testHasReturnsTrueForSetKey(): void
    {
        $this->registry->set('present', new \stdClass());
        $this->assertTrue($this->registry->has('present'));
    }

    public function testUnsetRemovesKey(): void
    {
        $this->registry->set('temp', new \stdClass());
        $this->assertTrue($this->registry->has('temp'));

        $this->registry->unset('temp');
        $this->assertFalse($this->registry->has('temp'));
        $this->assertNull($this->registry->get('temp'));
    }

    public function testUnsetOnMissingKeyDoesNotError(): void
    {
        $this->registry->unset('never_set');
        $this->assertFalse($this->registry->has('never_set'));
    }

    public function testMagicGetDelegatesToGet(): void
    {
        $obj = new \stdClass();
        $this->registry->set('magic', $obj);
        $this->assertSame($obj, $this->registry->magic);
    }

    public function testMagicGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->registry->nonexistent);
    }

    public function testMagicSetDelegatesToSet(): void
    {
        $obj = new \stdClass();
        $this->registry->magic_set = $obj;
        $this->assertSame($obj, $this->registry->get('magic_set'));
    }

    public function testMagicIssetDelegatesToHas(): void
    {
        $this->assertFalse(isset($this->registry->foo));

        $this->registry->set('foo', new \stdClass());
        $this->assertTrue(isset($this->registry->foo));
    }
}
