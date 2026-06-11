<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Tax;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class TaxTest extends TestCase
{
    /**
     * Build a Tax instance with a DB mock that returns the given rows for
     * the first query (setShippingAddress / setPaymentAddress / setStoreAddress).
     */
    private function buildTax(array $taxRateRows = []): Tax
    {
        $db = new class($taxRateRows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function query(string $sql): object {
                return DbResultFactory::many($this->rows);
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->build();

        return new Tax($registry);
    }

    private function fixedRateRow(): array
    {
        return [
            'tax_class_id' => 10,
            'tax_rate_id'  => 100,
            'name'         => 'Flat Tax',
            'rate'         => 5.00,
            'type'         => 'F',
            'priority'     => 1,
        ];
    }

    private function percentRateRow(): array
    {
        return [
            'tax_class_id' => 10,
            'tax_rate_id'  => 200,
            'name'         => 'VAT',
            'rate'         => 20.00,
            'type'         => 'P',
            'priority'     => 2,
        ];
    }

    // --- Constructor ---

    public function testConstructorDoesNotPopulateTaxRates(): void
    {
        $tax = $this->buildTax();
        // No rates loaded yet, calculate should return original value
        $this->assertSame(100.0, $tax->calculate(100.0, 10, true));
    }

    // --- setShippingAddress() ---

    public function testSetShippingAddressPopulatesTaxRates(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setShippingAddress(1, 1);

        $rates = $tax->getRates(100.0, 10);
        $this->assertCount(1, $rates);
        $this->assertSame(5.0, $rates[100]['amount']);
    }

    // --- setPaymentAddress() ---

    public function testSetPaymentAddressPopulatesTaxRates(): void
    {
        $tax = $this->buildTax([$this->percentRateRow()]);
        $tax->setPaymentAddress(1, 1);

        $rates = $tax->getRates(100.0, 10);
        $this->assertCount(1, $rates);
        $this->assertSame(20.0, $rates[200]['amount']);
    }

    // --- setStoreAddress() ---

    public function testSetStoreAddressPopulatesTaxRates(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setStoreAddress(1, 1);

        $rates = $tax->getRates(100.0, 10);
        $this->assertCount(1, $rates);
    }

    // --- calculate() ---

    public function testCalculateWithFixedRate(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setShippingAddress(1, 1);

        // 100 + 5 (fixed) = 105
        $this->assertEqualsWithDelta(105.0, $tax->calculate(100.0, 10, true), 0.001);
    }

    public function testCalculateWithPercentageRate(): void
    {
        $tax = $this->buildTax([$this->percentRateRow()]);
        $tax->setShippingAddress(1, 1);

        // 100 + (100 * 20/100) = 120
        $this->assertEqualsWithDelta(120.0, $tax->calculate(100.0, 10, true), 0.001);
    }

    public function testCalculateWithBothFixedAndPercentage(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow(), $this->percentRateRow()]);
        $tax->setShippingAddress(1, 1);

        // 100 + 5 (fixed) + 20 (20% of 100) = 125
        $this->assertEqualsWithDelta(125.0, $tax->calculate(100.0, 10, true), 0.001);
    }

    public function testCalculateReturnsSameValueWhenCalculateIsFalse(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setShippingAddress(1, 1);

        $this->assertSame(100.0, $tax->calculate(100.0, 10, false));
    }

    public function testCalculateReturnsSameValueWhenTaxClassIdIsZero(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setShippingAddress(1, 1);

        $this->assertSame(100.0, $tax->calculate(100.0, 0, true));
    }

    // --- getTax() ---

    public function testGetTaxReturnsJustTheTaxAmount(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setShippingAddress(1, 1);

        $this->assertEqualsWithDelta(5.0, $tax->getTax(100.0, 10), 0.001);
    }

    public function testGetTaxReturnsZeroForUnknownClass(): void
    {
        $tax = $this->buildTax();
        $this->assertSame(0.0, $tax->getTax(100.0, 999));
    }

    // --- getRates() ---

    public function testGetRatesReturnsEmptyForUnknownTaxClass(): void
    {
        $tax = $this->buildTax();
        $this->assertSame([], $tax->getRates(100.0, 999));
    }

    public function testGetRatesReturnsSingleEntryPerRateId(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow(), $this->percentRateRow()]);
        $tax->setShippingAddress(1, 1);

        $rates = $tax->getRates(100.0, 10);
        $this->assertCount(2, $rates);
        $this->assertArrayHasKey(100, $rates);
        $this->assertArrayHasKey(200, $rates);
    }

    // --- getRateName() ---

    public function testGetRateNameReturnsNameWhenFound(): void
    {
        $db = new class {
            public function query(string $sql): object {
                return DbResultFactory::one(['name' => 'Sales Tax']);
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $registry = (new RegistryBuilder())->with('db', $db)->build();
        $tax = new Tax($registry);

        $this->assertSame('Sales Tax', $tax->getRateName(1));
    }

    public function testGetRateNameReturnsFalseWhenNotFound(): void
    {
        $tax = $this->buildTax();
        $this->assertFalse($tax->getRateName(999));
    }

    // --- clear() ---

    public function testClearRemovesAllTaxRates(): void
    {
        $tax = $this->buildTax([$this->fixedRateRow()]);
        $tax->setShippingAddress(1, 1);

        $this->assertNotEmpty($tax->getRates(100.0, 10));

        $tax->clear();

        $this->assertSame([], $tax->getRates(100.0, 10));
    }
}
