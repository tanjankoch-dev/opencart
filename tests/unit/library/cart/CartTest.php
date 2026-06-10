<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Cart;
use Tests\Support\RegistryBuilder;

/**
 * Reference pattern for testing OpenCart library classes.
 *
 * All six Cart dependencies (db, config, customer, session, tax, weight)
 * are provided via RegistryBuilder. The DB mock returns empty result sets,
 * leaving Cart::$data = [] after construction. Tests then assert the pure
 * aggregation methods on an empty cart.
 */
class CartTest extends TestCase
{
    private \Opencart\System\Engine\Registry $registry;

    protected function setUp(): void
    {
        $this->registry = (new RegistryBuilder())->build();
    }

    /** An empty cart reports no products. */
    public function testHasProductsReturnsFalseForEmptyCart(): void
    {
        $cart = new Cart($this->registry);
        $this->assertFalse($cart->hasProducts());
    }

    /** An empty cart has a product count of zero. */
    public function testCountProductsReturnsZeroForEmptyCart(): void
    {
        $cart = new Cart($this->registry);
        $this->assertSame(0, $cart->countProducts());
    }

    /** An empty cart has a sub-total of zero. */
    public function testGetSubTotalReturnsZeroForEmptyCart(): void
    {
        $cart = new Cart($this->registry);
        $this->assertSame(0.0, $cart->getSubTotal());
    }

    /** has() returns false for a cart_id that was never added. */
    public function testHasReturnsFalseForUnknownCartId(): void
    {
        $cart = new Cart($this->registry);
        $this->assertFalse($cart->has(9999));
    }
}
