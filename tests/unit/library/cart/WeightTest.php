<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Weight;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class WeightTest extends TestCase
{
    private function buildWeight(array $rows = []): Weight
    {
        $result = $rows ? DbResultFactory::many($rows) : DbResultFactory::empty();

        $db = new class($result) {
            public function __construct(private object $r) {}
            public function query(string $sql): object { return $this->r; }
            public function escape(string $v): string { return addslashes($v); }
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

    private function lbRow(): array
    {
        return [
            'weight_class_id' => 2,
            'title'           => 'Pound',
            'unit'            => 'lb',
            'value'           => 2.20462000,
        ];
    }

    public function testConvertSameUnitReturnsSameValue(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame(5.0, $weight->convert(5.0, 1, 1));
    }

    public function testConvertBetweenUnits(): void
    {
        $weight = $this->buildWeight([$this->kgRow(), $this->lbRow()]);
        $result = $weight->convert(1.0, 1, 2);
        $this->assertEqualsWithDelta(2.20462, $result, 0.001);
    }

    public function testConvertUnknownFromUsesOne(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $result = $weight->convert(5.0, 999, 1);
        $this->assertSame(5.0, $result);
    }

    public function testConvertUnknownToUsesOne(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $result = $weight->convert(5.0, 1, 999);
        $this->assertSame(5.0, $result);
    }

    public function testGetUnitReturnsEmptyForUnknown(): void
    {
        $weight = $this->buildWeight();
        $this->assertSame('', $weight->getUnit(999));
    }

    public function testGetUnitReturnsUnit(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame('kg', $weight->getUnit(1));
    }

    public function testFormatWithKnownUnit(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame('5.00kg', $weight->format(5.0, 1));
    }

    public function testFormatWithUnknownUnit(): void
    {
        $weight = $this->buildWeight();
        $this->assertSame('5.00', $weight->format(5.0, 999));
    }

    public function testFormatCustomSeparators(): void
    {
        $weight = $this->buildWeight([$this->kgRow()]);
        $this->assertSame('1.000,50kg', $weight->format(1000.5, 1, ',', '.'));
    }
}
