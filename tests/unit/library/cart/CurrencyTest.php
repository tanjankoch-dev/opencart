<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Currency;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class CurrencyTest extends TestCase
{
    private function buildCurrency(array $currencyRows = []): Currency
    {
        $db = new class($currencyRows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function query(string $sql): object { return DbResultFactory::many($this->rows); }
            public function escape(string $v): string  { return addslashes($v); }
        };

        $language = new class {
            public function get(string $key): string {
                return match ($key) {
                    'decimal_point'  => '.',
                    'thousand_point' => ',',
                    default          => '',
                };
            }
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
            'decimal_place' => '2',
            'value'         => 1.00000000,
        ];
    }

    private function eurRow(): array
    {
        return [
            'currency_id'   => 2,
            'code'          => 'EUR',
            'title'         => 'Euro',
            'symbol_left'   => '',
            'symbol_right'  => '€',
            'decimal_place' => '2',
            'value'         => 0.85000000,
        ];
    }

    // --- Constructor ---

    public function testConstructorLoadsEmptyWhenNoRows(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertFalse($currency->has('USD'));
    }

    public function testConstructorLoadsCurrencies(): void
    {
        $currency = $this->buildCurrency([$this->usdRow(), $this->eurRow()]);
        $this->assertTrue($currency->has('USD'));
        $this->assertTrue($currency->has('EUR'));
    }

    // --- format() ---

    public function testFormatReturnsEmptyStringForUnknownCurrency(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame('', $currency->format(10.00, 'GBP'));
    }

    public function testFormatWithDefaultValueAndFormatTrue(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(10.50, 'USD');
        $this->assertSame('$10.50', $result);
    }

    public function testFormatWithExplicitValue(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(10.00, 'USD', 2.0);
        $this->assertSame('$20.00', $result);
    }

    public function testFormatWithFormatFalseReturnsFloat(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(10.00, 'USD', 0.0, false);
        $this->assertSame(10.0, $result);
    }

    public function testFormatWithSymbolRight(): void
    {
        $currency = $this->buildCurrency([$this->eurRow()]);
        $result = $currency->format(100.00, 'EUR');
        $this->assertStringEndsWith('€', $result);
    }

    public function testFormatWithSymbolLeft(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->format(5.00, 'USD');
        $this->assertStringStartsWith('$', $result);
    }

    // --- convert() ---

    public function testConvertBetweenKnownCurrencies(): void
    {
        $currency = $this->buildCurrency([$this->usdRow(), $this->eurRow()]);
        $result = $currency->convert(100.0, 'USD', 'EUR');
        $this->assertEqualsWithDelta(85.0, $result, 0.001);
    }

    public function testConvertWithUnknownFromCurrency(): void
    {
        $currency = $this->buildCurrency([$this->eurRow()]);
        $result = $currency->convert(100.0, 'GBP', 'EUR');
        // GBP not found => from=1, EUR value=0.85 => 100 * (0.85 / 1)
        $this->assertEqualsWithDelta(85.0, $result, 0.001);
    }

    public function testConvertWithUnknownToCurrency(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $result = $currency->convert(100.0, 'USD', 'GBP');
        // USD value=1, GBP not found => to=1 => 100 * (1 / 1)
        $this->assertEqualsWithDelta(100.0, $result, 0.001);
    }

    // --- getId() ---

    public function testGetIdReturnsIdForKnownCurrency(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame(1, $currency->getId('USD'));
    }

    public function testGetIdReturnsZeroForUnknown(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertSame(0, $currency->getId('XYZ'));
    }

    // --- getSymbolLeft() ---

    public function testGetSymbolLeftReturnsSymbol(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame('$', $currency->getSymbolLeft('USD'));
    }

    public function testGetSymbolLeftReturnsEmptyForUnknown(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertSame('', $currency->getSymbolLeft('XYZ'));
    }

    // --- getSymbolRight() ---

    public function testGetSymbolRightReturnsSymbol(): void
    {
        $currency = $this->buildCurrency([$this->eurRow()]);
        $this->assertSame('€', $currency->getSymbolRight('EUR'));
    }

    public function testGetSymbolRightReturnsEmptyForUnknown(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertSame('', $currency->getSymbolRight('XYZ'));
    }

    // --- getDecimalPlace() ---

    public function testGetDecimalPlaceReturnsValue(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertSame(2, $currency->getDecimalPlace('USD'));
    }

    public function testGetDecimalPlaceReturnsZeroForUnknown(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertSame(0, $currency->getDecimalPlace('XYZ'));
    }

    // --- getValue() ---

    public function testGetValueReturnsValue(): void
    {
        $currency = $this->buildCurrency([$this->eurRow()]);
        $this->assertEqualsWithDelta(0.85, $currency->getValue('EUR'), 0.001);
    }

    public function testGetValueReturnsZeroForUnknown(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertSame(0.0, $currency->getValue('XYZ'));
    }

    // --- has() ---

    public function testHasReturnsTrueForLoaded(): void
    {
        $currency = $this->buildCurrency([$this->usdRow()]);
        $this->assertTrue($currency->has('USD'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $currency = $this->buildCurrency([]);
        $this->assertFalse($currency->has('USD'));
    }
}
