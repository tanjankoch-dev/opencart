<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Weight;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class WeightTest extends TestCase
{
    private function buildWeight(array $weightRows = []): Weight
    {
        $db = new class($weightRows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function query(string $sql): object { return DbResultFactory::many($this->rows); }
            public function escape(string $v): string  { return addslashes($v); }
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->build();

        return new Weight($registry);
    }

    private function kgRow(): array
    {
        return [
            'weight_class_id' => 1,
            'title'           => 'Kilogram',
            'unit'            => 'kg',
            'value'           => 1.00000000,
        ];
    }

    private function gRow(): array
    {
        return [
            'weight_class_id' => 2,
            'title'           => 'Gram',
            'unit'            => 'g',
            'value'           => 1000.00000000,
        ];
    }

    // --- Constructor ---

    public function testConstructorLoadsWeightClasses(): void
    {
        $weight = $this->buildWeight([$this->kgRow(), $this->gRow()]);
        $this->assertSame('kg', $weight->getUnit(1));
        $this->assertSame('g', $weight->getUnit(2));
    }

    public function testConstructorWithNoRows(): void
    {
        $weight = $this->buildWeight([]);
        $this->assertSame('', $weight->getUnit(1));
    }

    // --- convert() ---

    public function testConvertIdentityReturnsOriginalValue(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame(5.0, $weight->convert(5.0, 1, 1));
    }

    public function testConvertBetweenKnownClasses(): void
    {
        $weight = $this->buildWeight([$this->kgRow(), $this->gRow()]);
        // 1 kg -> g: 1.0 * (1000 / 1) = 1000
        $this->assertEqualsWithDelta(1000.0, $weight->convert(1.0, 1, 2), 0.001);
    }

    public function testConvertGramToKg(): void
    {
        $weight = $this->buildWeight([$this->kgRow(), $this->gRow()]);
        // 1000 g -> kg: 1000 * (1 / 1000) = 1
        $this->assertEqualsWithDelta(1.0, $weight->convert(1000.0, 2, 1), 0.001);
    }

    public function testConvertWithUnknownFromFallsBackToOne(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        // from=999 (unknown => 1), to=1 (kg, value=1) => 5 * (1/1) = 5
        $this->assertEqualsWithDelta(5.0, $weight->convert(5.0, 999, 1), 0.001);
    }

    public function testConvertWithUnknownToFallsBackToOne(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        // from=1 (kg, value=1), to=999 (unknown => 1) => 5 * (1/1) = 5
        $this->assertEqualsWithDelta(5.0, $weight->convert(5.0, 1, 999), 0.001);
    }

    // --- format() ---

    public function testFormatKnownClassAppendsUnit(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame('5.00kg', $weight->format(5.0, 1));
    }

    public function testFormatUnknownClassReturnsNumberOnly(): void
    {
        $weight = $this->buildWeight([]);
        $this->assertSame('5.00', $weight->format(5.0, 999));
    }

    public function testFormatCustomSeparators(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame('1.234,56kg', $weight->format(1234.56, 1, ',', '.'));
    }

    // --- getUnit() ---

    public function testGetUnitReturnsUnitForKnownClass(): void
    {
        $weight = $this->buildWeight([$this->gRow()]);
        $this->assertSame('g', $weight->getUnit(2));
    }

    public function testGetUnitReturnsEmptyForUnknownClass(): void
    {
        $weight = $this->buildWeight([]);
        $this->assertSame('', $weight->getUnit(999));
    }
}
