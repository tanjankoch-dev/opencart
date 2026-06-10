<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Currency;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class CurrencyTest extends TestCase
{
    private function buildCurrency(array $rows = []): Currency
    {
        $language = new class {
            public function get(string $key): string {
                return match ($key) {
                    'decimal_point' => '.',
                    'thousand_point' => ',',
                    default => '',
                };
            }
        };

        $result = $rows ? DbResultFactory::many($rows) : DbResultFactory::empty();

        $db = new class($result) {
            public function __construct(private object $r) {}
            public function query(string $sql): object { return $this->r; }
            public function escape(string $v): string { return addslashes($v); }
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('language', $language)
            ->build();

        return new Currency($registry);
    }

    private function usdRow(): array
    {
        return [
            'currency_id'   => 1,
            'code'          => 'USD',
            'title'         => 'US Dollar',
            'symbol_left'   => '$',
            'symbol_right'  => '',
            'decimal_place' => 2,
            'value'         => 1.00000000,
        ];
    }

    public function testHasReturnsFalseForUnknownCurrency(): void
    {
        $currency = $this->buildCurrency();
        $this->assertFalse($currency->has('XYZ'));
    }

    public function testHasReturnsTrueForKnownCurrency(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertTrue($currency->has('USD'));
    }

    public function testGetValueReturnsZeroForUnknown(): void
    {
        $currency = $this->buildCurrency();
        $this->assertSame(0.0, $currency->getValue('USD'));
    }

    public function testGetValueReturnsRate(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame(1.0, $currency->getValue('USD'));
    }

    public function testGetIdReturnsZeroForUnknown(): void
    {
        $currency = $this->buildCurrency();
        $this->assertSame(0, $currency->getId('USD'));
    }

    public function testGetIdReturnsCurrencyId(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame(1, $currency->getId('USD'));
    }

    public function testGetSymbolLeftReturnsEmptyForUnknown(): void
    {
        $currency = $this->buildCurrency();
        $this->assertSame('', $currency->getSymbolLeft('USD'));
    }

    public function testGetSymbolLeftReturnsSymbol(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame('$', $currency->getSymbolLeft('USD'));
    }

    public function testGetSymbolRightReturnsEmptyForUnknown(): void
    {
        $currency = $this->buildCurrency();
        $this->assertSame('', $currency->getSymbolRight('USD'));
    }

    public function testGetDecimalPlaceReturnsZeroForUnknown(): void
    {
        $currency = $this->buildCurrency();
        $this->assertSame(0, $currency->getDecimalPlace('USD'));
    }

    public function testGetDecimalPlaceReturnsValue(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame(2, $currency->getDecimalPlace('USD'));
    }

    public function testFormatReturnsEmptyStringForUnknownCurrency(): void
    {
        $currency = $this->buildCurrency();
        $this->assertSame('', $currency->format(10.0, 'XYZ'));
    }

    public function testFormatReturnsFormattedString(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(10.50, 'USD');
        $this->assertSame('$10.50', $result);
    }

    public function testFormatWithoutFormattingReturnsFloat(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(10.50, 'USD', 0, false);
        $this->assertSame(10.5, $result);
    }

    public function testFormatWithExplicitValue(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(10.0, 'USD', 2.0);
        $this->assertSame('$20.00', $result);
    }

    public function testConvertBetweenCurrencies(): void
    {
        $eur = [
            'currency_id'   => 2,
            'code'          => 'EUR',
            'title'         => 'Euro',
            'symbol_left'   => '',
            'symbol_right'  => '€',
            'decimal_place' => 2,
            'value'         => 0.85,
        ];

        $currency = $this->buildCurrency([$this->usdRow(), $eur]);
        $result = $currency->convert(100.0, 'USD', 'EUR');
        $this->assertEqualsWithDelta(85.0, $result, 0.001);
    }

    public function testConvertWithUnknownFromUsesOne(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->convert(50.0, 'UNKNOWN', 'USD');
        $this->assertSame(50.0, $result);
    }

    public function testConvertWithUnknownToUsesOne(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->convert(50.0, 'USD', 'UNKNOWN');
        $this->assertSame(50.0, $result);
    }
}
