<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use Opencart\System\Library\Cart\Tax;
use PHPUnit\Framework\TestCase;
use Tests\Support\DbResultFactory;
use Tests\Support\RegistryBuilder;

class TaxTest extends TestCase {
	private function buildTax(array $dbRows = []): Tax {
		$result = DbResultFactory::many($dbRows ?: []);

		$db = new class ($result) {
			public function __construct(private object $result) {}

			public function query(string $sql): object {
				return $this->result;
			}

			public function escape(string $v): string {
				return addslashes($v);
			}
		};

		$registry = (new RegistryBuilder())
			->with('db', $db)
			->build();

		return new Tax($registry);
	}

	private function taxRowFixedRate(): array {
		return [
			'tax_class_id' => 9,
			'tax_rate_id'  => 50,
			'name'         => 'Eco Tax',
			'rate'         => 2.00,
			'type'         => 'F',
			'priority'     => 1,
		];
	}

	private function taxRowPercentRate(): array {
		return [
			'tax_class_id' => 9,
			'tax_rate_id'  => 51,
			'name'         => 'VAT',
			'rate'         => 20.00,
			'type'         => 'P',
			'priority'     => 2,
		];
	}

	public function testGetRatesReturnsEmptyWhenNoRatesLoaded(): void {
		$tax = $this->buildTax();
		static::assertSame([], $tax->getRates(100.0, 9));
	}

	public function testCalculateReturnsValueWhenNoTaxClassMatch(): void {
		$tax = $this->buildTax();
		static::assertEqualsWithDelta(100.0, $tax->calculate(100.0, 999), 0.01);
	}

	public function testCalculateReturnsSameValueWhenCalculateFlagIsFalse(): void {
		$tax = $this->buildTax();
		static::assertEqualsWithDelta(100.0, $tax->calculate(100.0, 9, false), 0.01);
	}

	public function testSetShippingAddressLoadsFixedRate(): void {
		$tax = $this->buildTax([$this->taxRowFixedRate()]);
		$tax->setShippingAddress(1, 1);

		$rates = $tax->getRates(100.0, 9);
		static::assertCount(1, $rates);
		static::assertEqualsWithDelta(2.00, $rates[50]['amount'], 0.01);
	}

	public function testSetPaymentAddressLoadsPercentRate(): void {
		$tax = $this->buildTax([$this->taxRowPercentRate()]);
		$tax->setPaymentAddress(1, 1);

		$rates = $tax->getRates(100.0, 9);
		static::assertCount(1, $rates);
		static::assertEqualsWithDelta(20.0, $rates[51]['amount'], 0.01);
	}

	public function testCalculateWithFixedRate(): void {
		$tax = $this->buildTax([$this->taxRowFixedRate()]);
		$tax->setStoreAddress(1, 1);

		static::assertEqualsWithDelta(102.0, $tax->calculate(100.0, 9), 0.01);
	}

	public function testCalculateWithPercentRate(): void {
		$tax = $this->buildTax([$this->taxRowPercentRate()]);
		$tax->setStoreAddress(1, 1);

		static::assertEqualsWithDelta(120.0, $tax->calculate(100.0, 9), 0.01);
	}

	public function testCalculateWithMultipleRates(): void {
		$tax = $this->buildTax([$this->taxRowFixedRate(), $this->taxRowPercentRate()]);
		$tax->setShippingAddress(1, 1);

		// Fixed 2.00 + Percent 20% of 100 = 22.00 total tax
		static::assertEqualsWithDelta(122.0, $tax->calculate(100.0, 9), 0.01);
	}

	public function testGetTaxReturnsOnlyTaxAmount(): void {
		$tax = $this->buildTax([$this->taxRowPercentRate()]);
		$tax->setShippingAddress(1, 1);

		static::assertEqualsWithDelta(20.0, $tax->getTax(100.0, 9), 0.01);
	}

	public function testGetTaxReturnsZeroForUnknownClass(): void {
		$tax = $this->buildTax();
		static::assertEqualsWithDelta(0.0, $tax->getTax(100.0, 999), 0.01);
	}

	public function testGetRateNameReturnsFalseWhenNotFound(): void {
		$tax = $this->buildTax();
		static::assertFalse($tax->getRateName(9999));
	}

	public function testGetRateNameReturnsNameWhenFound(): void {
		$nameRow = ['name' => 'VAT'];
		$result = DbResultFactory::one($nameRow);

		$db = new class ($result) {
			public function __construct(private object $result) {}

			public function query(string $sql): object {
				return $this->result;
			}

			public function escape(string $v): string {
				return addslashes($v);
			}
		};

		$registry = (new RegistryBuilder())
			->with('db', $db)
			->build();

		$tax = new Tax($registry);
		static::assertSame('VAT', $tax->getRateName(51));
	}

	public function testClearRemovesAllRates(): void {
		$tax = $this->buildTax([$this->taxRowFixedRate()]);
		$tax->setShippingAddress(1, 1);

		static::assertNotEmpty($tax->getRates(100.0, 9));

		$tax->clear();

		static::assertEmpty($tax->getRates(100.0, 9));
	}
}
