<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Length;
use Tests\Support\DbResultFactory;
use Tests\Support\RegistryBuilder;

/**
 * Tests for Length library class.
 *
 * Mocks db->query() to return length-class rows so the constructor populates
 * the internal $lengths array.  Tests then exercise convert(), format(), and
 * getUnit() with known classes, unknown classes, and identity conversions.
 */
class LengthTest extends TestCase
{
    /**
     * Build a registry whose db->query() returns the given length-class rows.
     */
    private function buildRegistry(array $rows = []): \Opencart\System\Engine\Registry
    {
        $result = empty($rows)
            ? DbResultFactory::empty()
            : DbResultFactory::many($rows);

        $db = new class($result) {
            public function __construct(private object $result) {}
            public function query(string $sql): object { return $this->result; }
            public function escape(string $v): string  { return addslashes($v); }
        };

        return (new RegistryBuilder())
            ->with('db', $db)
            ->build();
    }

    /**
     * Standard length-class rows: cm (base, value=1.0), mm (0.1), m (100.0).
     */
    private function standardRows(): array
    {
        return [
            ['length_class_id' => 1, 'title' => 'Centimeter', 'unit' => 'cm', 'value' => '1.00000000'],
            ['length_class_id' => 2, 'title' => 'Millimeter', 'unit' => 'mm', 'value' => '0.10000000'],
            ['length_class_id' => 3, 'title' => 'Meter',      'unit' => 'm',  'value' => '100.00000000'],
        ];
    }

    // ── Constructor ──────────────────────────────────────────────────

    public function testConstructorWithNoRows(): void
    {
        $length = new Length($this->buildRegistry([]));
        // No rows → getUnit for any id returns ''
        $this->assertSame('', $length->getUnit(1));
    }

    public function testConstructorPopulatesLengths(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('cm', $length->getUnit(1));
        $this->assertSame('mm', $length->getUnit(2));
        $this->assertSame('m',  $length->getUnit(3));
    }

    // ── convert() ────────────────────────────────────────────────────

    public function testConvertIdentityReturnsSameValue(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame(42.5, $length->convert(42.5, 1, 1));
    }

    public function testConvertBetweenKnownClasses(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        // 10 cm → mm:  10 * (0.1 / 1.0) = 1.0
        $this->assertEqualsWithDelta(1.0, $length->convert(10.0, 1, 2), 0.0001);
    }

    public function testConvertCmToMeter(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        // 1 cm → m: 1 * (100.0 / 1.0) = 100.0
        $this->assertEqualsWithDelta(100.0, $length->convert(1.0, 1, 3), 0.0001);
    }

    public function testConvertMmToMeter(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        // 5 mm → m: 5 * (100.0 / 0.1) = 5000.0
        $this->assertEqualsWithDelta(5000.0, $length->convert(5.0, 2, 3), 0.0001);
    }

    public function testConvertFromUnknownClassFallsBackToOne(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        // Unknown from (id=999), to=cm (value=1.0): 5 * (1.0 / 1) = 5.0
        $this->assertEqualsWithDelta(5.0, $length->convert(5.0, 999, 1), 0.0001);
    }

    public function testConvertToUnknownClassFallsBackToOne(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        // from=cm (value=1.0), unknown to (id=999): 5 * (1 / 1.0) = 5.0
        $this->assertEqualsWithDelta(5.0, $length->convert(5.0, 1, 999), 0.0001);
    }

    public function testConvertBothUnknownFallsBackToOne(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        // Both unknown: 7.5 * (1 / 1) = 7.5
        $this->assertEqualsWithDelta(7.5, $length->convert(7.5, 888, 999), 0.0001);
    }

    // ── format() ─────────────────────────────────────────────────────

    public function testFormatKnownClassAppendsUnit(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('12.50cm', $length->format(12.5, 1));
    }

    public function testFormatUnknownClassOmitsUnit(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('12.50', $length->format(12.5, 999));
    }

    public function testFormatCustomDecimalAndThousand(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('1.234,56mm', $length->format(1234.56, 2, ',', '.'));
    }

    public function testFormatDefaultSeparators(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('1,234.56m', $length->format(1234.56, 3));
    }

    // ── getUnit() ────────────────────────────────────────────────────

    public function testGetUnitKnownClass(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('cm', $length->getUnit(1));
    }

    public function testGetUnitUnknownClassReturnsEmpty(): void
    {
        $length = new Length($this->buildRegistry($this->standardRows()));
        $this->assertSame('', $length->getUnit(999));
    }

    public function testGetUnitEmptyDbReturnsEmpty(): void
    {
        $length = new Length($this->buildRegistry([]));
        $this->assertSame('', $length->getUnit(1));
    }
}
