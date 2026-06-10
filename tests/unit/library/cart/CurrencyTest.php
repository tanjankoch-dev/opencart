<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use Opencart\System\Library\Cart\Currency;
use PHPUnit\Framework\TestCase;
use Tests\Support\DbResultFactory;
use Tests\Support\RegistryBuilder;

class CurrencyTest extends TestCase {
	private function buildCurrency(array $rows = []): Currency {
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
			->with('language', $language)
			->build();

		// Replace the db mock so the constructor query returns our rows.
		$result = DbResultFactory::many($rows);
		$db = new class ($result) {
			public function __construct(private object $result) {}

			public function query(string $sql): object {
				return $this->result;
			}

			public function escape(string $v): string {
				return addslashes($v);
			}
		};

		$registry->set('db', $db);

		return new Currency($registry);
	}

	private function usdRow(): array {
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

	private function eurRow(): array {
		return [
			'currency_id'   => 2,
			'code'          => 'EUR',
			'title'         => 'Euro',
			'symbol_left'   => '',
			'symbol_right'  => '€',
			'decimal_place' => 2,
			'value'         => 0.95000000,
		];
	}

	public function testHasReturnsTrueForLoadedCurrency(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		static::assertTrue($currency->has('USD'));
	}

	public function testHasReturnsFalseForUnknownCurrency(): void {
		$currency = $this->buildCurrency([]);
		static::assertFalse($currency->has('GBP'));
	}

	public function testGetIdReturnsCorrectId(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		static::assertSame(1, $currency->getId('USD'));
	}

	public function testGetIdReturnsZeroForUnknownCurrency(): void {
		$currency = $this->buildCurrency([]);
		static::assertSame(0, $currency->getId('GBP'));
	}

	public function testGetSymbolLeft(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		static::assertSame('$', $currency->getSymbolLeft('USD'));
	}

	public function testGetSymbolLeftReturnsEmptyForUnknown(): void {
		$currency = $this->buildCurrency([]);
		static::assertSame('', $currency->getSymbolLeft('GBP'));
	}

	public function testGetSymbolRight(): void {
		$currency = $this->buildCurrency([$this->eurRow()]);
		static::assertSame('€', $currency->getSymbolRight('EUR'));
	}

	public function testGetSymbolRightReturnsEmptyForUnknown(): void {
		$currency = $this->buildCurrency([]);
		static::assertSame('', $currency->getSymbolRight('GBP'));
	}

	public function testGetDecimalPlace(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		static::assertSame(2, $currency->getDecimalPlace('USD'));
	}

	public function testGetDecimalPlaceReturnsZeroForUnknown(): void {
		$currency = $this->buildCurrency([]);
		static::assertSame(0, $currency->getDecimalPlace('GBP'));
	}

	public function testGetValue(): void {
		$currency = $this->buildCurrency([$this->eurRow()]);
		static::assertEqualsWithDelta(0.95, $currency->getValue('EUR'), 0.0001);
	}

	public function testGetValueReturnsZeroForUnknown(): void {
		$currency = $this->buildCurrency([]);
		static::assertEqualsWithDelta(0.0, $currency->getValue('GBP'), 0.0001);
	}

	public function testFormatWithSymbolLeft(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		$formatted = $currency->format(10.50, 'USD');
		static::assertSame('$10.50', $formatted);
	}

	public function testFormatWithSymbolRight(): void {
		$currency = $this->buildCurrency([$this->eurRow()]);
		$formatted = $currency->format(10.00, 'EUR');
		static::assertSame('9.50€', $formatted);
	}

	public function testFormatReturnsEmptyForUnknownCurrency(): void {
		$currency = $this->buildCurrency([]);
		static::assertSame('', $currency->format(10.00, 'GBP'));
	}

	public function testFormatWithoutFormattingReturnsFloat(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		$result = $currency->format(10.50, 'USD', 0, false);
		static::assertIsFloat($result);
		static::assertEqualsWithDelta(10.50, $result, 0.01);
	}

	public function testFormatWithExplicitValue(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		$result = $currency->format(10.00, 'USD', 2.0, false);
		static::assertEqualsWithDelta(20.00, $result, 0.01);
	}

	public function testConvertBetweenCurrencies(): void {
		$currency = $this->buildCurrency([$this->usdRow(), $this->eurRow()]);
		$result = $currency->convert(100.0, 'USD', 'EUR');
		static::assertEqualsWithDelta(95.0, $result, 0.01);
	}

	public function testConvertWithUnknownFromDefaultsToOne(): void {
		$currency = $this->buildCurrency([$this->eurRow()]);
		$result = $currency->convert(100.0, 'UNKNOWN', 'EUR');
		static::assertEqualsWithDelta(95.0, $result, 0.01);
	}

	public function testConvertWithUnknownToDefaultsToOne(): void {
		$currency = $this->buildCurrency([$this->usdRow()]);
		$result = $currency->convert(100.0, 'USD', 'UNKNOWN');
		static::assertEqualsWithDelta(100.0, $result, 0.01);
	}
}
