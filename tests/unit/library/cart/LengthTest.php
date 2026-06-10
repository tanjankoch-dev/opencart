<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Length;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class LengthTest extends TestCase
{
    private function buildLength(array $rows = []): Length
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

        return new Length($registry);
    }

    private function cmRow(): array
    {
        return [
            'length_class_id' => 1,
            'title'           => 'Centimeter',
            'unit'            => 'cm',
            'value'           => 1.00000000,
        ];
    }

    private function inRow(): array
    {
        return [
            'length_class_id' => 2,
            'title'           => 'Inch',
            'unit'            => 'in',
            'value'           => 0.39370000,
        ];
    }

    public function testConvertSameUnitReturnsSameValue(): void
    {
        $length = $this->buildLength([$this->cmRow()]);
        $this->assertSame(10.0, $length->convert(10.0, 1, 1));
    }

    public function testConvertBetweenUnits(): void
    {
        $length = $this->buildLength([$this->cmRow(), $this->inRow()]);
        $result = $length->convert(1.0, 1, 2);
        $this->assertEqualsWithDelta(0.3937, $result, 0.001);
    }

    public function testConvertUnknownFromUsesOne(): void
    {
        $length = $this->buildLength([$this->cmRow()]);
        $result = $length->convert(5.0, 999, 1);
        $this->assertSame(5.0, $result);
    }

    public function testConvertUnknownToUsesOne(): void
    {
        $length = $this->buildLength([$this->cmRow()]);
        $result = $length->convert(5.0, 1, 999);
        $this->assertSame(5.0, $result);
    }

    public function testGetUnitReturnsEmptyForUnknown(): void
    {
        $length = $this->buildLength();
        $this->assertSame('', $length->getUnit(999));
    }

    public function testGetUnitReturnsUnit(): void
    {
        $length = $this->buildLength([$this->cmRow()]);
        $this->assertSame('cm', $length->getUnit(1));
    }

    public function testFormatWithKnownUnit(): void
    {
        $length = $this->buildLength([$this->cmRow()]);
        $this->assertSame('10.00cm', $length->format(10.0, 1));
    }

    public function testFormatWithUnknownUnit(): void
    {
        $length = $this->buildLength();
        $this->assertSame('10.00', $length->format(10.0, 999));
    }

    public function testFormatCustomSeparators(): void
    {
        $length = $this->buildLength([$this->cmRow()]);
        $this->assertSame('1.000,50cm', $length->format(1000.5, 1, ',', '.'));
    }
}
