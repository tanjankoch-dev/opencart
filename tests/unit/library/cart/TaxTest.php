<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Tax;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class TaxTest extends TestCase
{
    private function buildTax(object $dbResult = null): Tax
    {
        $result = $dbResult ?? DbResultFactory::empty();

        $db = new class($result) {
            public function __construct(private object $r) {}
            public function query(string $sql): object { return $this->r; }
            public function escape(string $v): string { return addslashes($v); }
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->build();

        return new Tax($registry);
    }

    public function testGetRatesReturnsEmptyForUnknownTaxClass(): void
    {
        $tax = $this->buildTax();
        $this->assertSame([], $tax->getRates(100.0, 999));
    }

    public function testCalculateReturnsValueWhenNoTaxClass(): void
    {
        $tax = $this->buildTax();
        $this->assertSame(100.0, $tax->calculate(100.0, 0));
    }

    public function testCalculateReturnsValueWhenCalculateIsFalse(): void
    {
        $tax = $this->buildTax();
        $this->assertSame(100.0, $tax->calculate(100.0, 1, false));
    }

    public function testSetShippingAddressPopulatesRates(): void
    {
        $rows = [
            [
                'tax_class_id' => 1,
                'tax_rate_id'  => 10,
                'name'         => 'VAT',
                'rate'         => 20,
                'type'         => 'P',
                'priority'     => 1,
            ],
        ];

        $tax = $this->buildTax(DbResultFactory::many($rows));
        $tax->setShippingAddress(1, 1);

        $rates = $tax->getRates(100.0, 1);
        $this->assertCount(1, $rates);
        $this->assertSame(20.0, $rates[10]['amount']);
    }

    public function testCalculateAddsPercentageTax(): void
    {
        $rows = [
            [
                'tax_class_id' => 1,
                'tax_rate_id'  => 10,
                'name'         => 'VAT',
                'rate'         => 10,
                'type'         => 'P',
                'priority'     => 1,
            ],
        ];

        $tax = $this->buildTax(DbResultFactory::many($rows));
        $tax->setShippingAddress(1, 1);

        $this->assertSame(110.0, $tax->calculate(100.0, 1));
    }

    public function testCalculateAddsFixedTax(): void
    {
        $rows = [
            [
                'tax_class_id' => 2,
                'tax_rate_id'  => 20,
                'name'         => 'Eco',
                'rate'         => 5,
                'type'         => 'F',
                'priority'     => 1,
            ],
        ];

        $tax = $this->buildTax(DbResultFactory::many($rows));
        $tax->setPaymentAddress(1, 1);

        $this->assertSame(105.0, $tax->calculate(100.0, 2));
    }

    public function testGetTaxReturnsOnlyTaxAmount(): void
    {
        $rows = [
            [
                'tax_class_id' => 1,
                'tax_rate_id'  => 10,
                'name'         => 'VAT',
                'rate'         => 20,
                'type'         => 'P',
                'priority'     => 1,
            ],
        ];

        $tax = $this->buildTax(DbResultFactory::many($rows));
        $tax->setStoreAddress(1, 1);

        $this->assertSame(20.0, $tax->getTax(100.0, 1));
    }

    public function testClearRemovesAllRates(): void
    {
        $rows = [
            [
                'tax_class_id' => 1,
                'tax_rate_id'  => 10,
                'name'         => 'VAT',
                'rate'         => 10,
                'type'         => 'P',
                'priority'     => 1,
            ],
        ];

        $tax = $this->buildTax(DbResultFactory::many($rows));
        $tax->setShippingAddress(1, 1);
        $tax->clear();

        $this->assertSame([], $tax->getRates(100.0, 1));
    }

    public function testGetRateNameReturnsFalseWhenNotFound(): void
    {
        $tax = $this->buildTax(DbResultFactory::empty());
        $this->assertFalse($tax->getRateName(999));
    }

    public function testGetRateNameReturnsName(): void
    {
        $result = DbResultFactory::one(['name' => 'VAT']);
        $tax = $this->buildTax($result);
        $this->assertSame('VAT', $tax->getRateName(10));
    }
}
