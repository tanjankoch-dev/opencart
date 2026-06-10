<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Opencart\System\Engine\Registry;
use Opencart\System\Library\Cart\Cart;

/**
 * Reference pattern for testing OpenCart library classes.
 *
 * Strategy:
 *  - Build a Registry and populate it with PHPUnit mocks/stubs.
 *  - The DB mock returns an empty result (no rows) so Cart::$data stays [].
 *  - Tests then assert the pure aggregation methods on an empty cart.
 */
class CartTest extends TestCase {
    private Registry $registry;

    protected function setUp(): void {
        // --- db mock: every query() call returns an object with rows=[] / num_rows=0 ---
        $emptyResult = new \stdClass();
        $emptyResult->rows    = [];
        $emptyResult->row     = [];
        $emptyResult->num_rows = 0;

        /** @var MockObject $db */
        $db = $this->createMock(\stdClass::class);
        // Cart calls $this->db->query(...) and $this->db->escape(...)
        // We use a generic mock; add the methods we need via getMockBuilder if stdClass
        // doesn't work — use an anonymous class instead:
        $db = new class($emptyResult) {
            public function __construct(private readonly object $result) {}
            public function query(string $sql): object { return $this->result; }
            public function escape(string $value): string { return addslashes($value); }
        };

        // --- config mock ---
        $config = $this->createStub(\stdClass::class);
        // Use an anonymous class so get() can handle any key
        $config = new class {
            public function get(string $key): mixed {
                return match ($key) {
                    'config_store_id'          => 0,
                    'config_language_id'       => 1,
                    'config_customer_group_id' => 1,
                    'config_weight_class_id'   => 1,
                    'config_tax'               => false,
                    'session_expire'           => 86400,
                    default                    => null,
                };
            }
        };

        // --- customer mock ---
        $customer = new class {
            public function isLogged(): bool { return false; }
            public function getId(): int     { return 0; }
        };

        // --- session mock ---
        $session = new class {
            public function getId(): string { return 'test-session-id'; }
        };

        // --- tax stub (not exercised in empty-cart tests) ---
        $tax = new class {
            public function getRates(float $price, int $taxClassId): array { return []; }
            public function calculate(float $price, int $taxClassId, bool $apply): float { return $price; }
        };

        // --- weight stub ---
        $weight = new class {
            public function convert(float $value, int $from, int $to): float { return $value; }
        };

        $this->registry = new Registry();
        $this->registry->set('db',       $db);
        $this->registry->set('config',   $config);
        $this->registry->set('customer', $customer);
        $this->registry->set('session',  $session);
        $this->registry->set('tax',      $tax);
        $this->registry->set('weight',   $weight);
    }

    /** An empty cart reports no products. */
    public function testHasProductsReturnsFalseForEmptyCart(): void {
        $cart = new Cart($this->registry);
        $this->assertFalse($cart->hasProducts());
    }

    /** An empty cart has a product count of zero. */
    public function testCountProductsReturnsZeroForEmptyCart(): void {
        $cart = new Cart($this->registry);
        $this->assertSame(0, $cart->countProducts());
    }

    /** An empty cart has a sub-total of zero. */
    public function testGetSubTotalReturnsZeroForEmptyCart(): void {
        $cart = new Cart($this->registry);
        $this->assertSame(0.0, $cart->getSubTotal());
    }

    /** has() returns false for a cart_id that was never added. */
    public function testHasReturnsFalseForUnknownCartId(): void {
        $cart = new Cart($this->registry);
        $this->assertFalse($cart->has(9999));
    }
}
