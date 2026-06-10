<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use Opencart\System\Library\Cart\Customer;
use PHPUnit\Framework\TestCase;
use Tests\Support\DbResultFactory;
use Tests\Support\RegistryBuilder;

/**
 * Tests for the Customer library class.
 *
 * The Customer constructor reads session data and hits the DB, so we provide
 * tailored mocks for each scenario. Helper functions oc_get_ip / oc_strtolower
 * are loaded once from the OpenCart helper file.
 */
class CustomerTest extends TestCase {
	public static function setUpBeforeClass(): void {
		$helper = DIR_SYSTEM . 'helper/general.php';

		if (is_file($helper)) {
			require_once $helper;
		}

		if (!isset($_SERVER['REMOTE_ADDR'])) {
			$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		}
	}

	private function buildCustomerNotLoggedIn(): Customer {
		$registry = (new RegistryBuilder())->build();

		$session = new \stdClass();
		$session->data = [];

		$request = new \stdClass();
		$request->server = ['REMOTE_ADDR' => '127.0.0.1'];

		$registry->set('session', $session);
		$registry->set('request', $request);

		return new Customer($registry);
	}

	private function buildCustomerLoggedIn(): Customer {
		$row = [
			'customer_id'       => 42,
			'firstname'         => 'John',
			'lastname'          => 'Doe',
			'customer_group_id' => 1,
			'email'             => 'john@example.com',
			'telephone'         => '555-1234',
			'newsletter'        => false,
			'safe'              => true,
			'commenter'         => true,
		];

		$result = DbResultFactory::one($row);

		$db = new class ($result) {
			public function __construct(private object $result) {}

			public function query(string $sql): object {
				return $this->result;
			}

			public function escape(string $v): string {
				return addslashes($v);
			}
		};

		$session = new \stdClass();
		$session->data = ['customer_id' => 42];

		$request = new \stdClass();
		$request->server = ['REMOTE_ADDR' => '127.0.0.1'];

		$registry = (new RegistryBuilder())
			->with('db', $db)
			->with('session', $session)
			->with('request', $request)
			->build();

		return new Customer($registry);
	}

	public function testNotLoggedInByDefault(): void {
		$customer = $this->buildCustomerNotLoggedIn();
		static::assertFalse($customer->isLogged());
	}

	public function testGetIdReturnsZeroWhenNotLoggedIn(): void {
		$customer = $this->buildCustomerNotLoggedIn();
		static::assertSame(0, $customer->getId());
	}

	public function testGettersReturnDefaultsWhenNotLoggedIn(): void {
		$customer = $this->buildCustomerNotLoggedIn();
		static::assertSame('', $customer->getFirstName());
		static::assertSame('', $customer->getLastName());
		static::assertSame(0, $customer->getGroupId());
		static::assertSame('', $customer->getEmail());
		static::assertSame('', $customer->getTelephone());
		static::assertFalse($customer->getNewsletter());
		static::assertFalse($customer->isSafe());
		static::assertFalse($customer->isCommenter());
	}

	public function testLoggedInCustomerIsLogged(): void {
		$customer = $this->buildCustomerLoggedIn();
		static::assertTrue($customer->isLogged());
	}

	public function testLoggedInCustomerGetters(): void {
		$customer = $this->buildCustomerLoggedIn();
		static::assertSame(42, $customer->getId());
		static::assertSame('John', $customer->getFirstName());
		static::assertSame('Doe', $customer->getLastName());
		static::assertSame(1, $customer->getGroupId());
		static::assertSame('john@example.com', $customer->getEmail());
		static::assertSame('555-1234', $customer->getTelephone());
	}

	public function testLoggedInCustomerBooleanGetters(): void {
		$customer = $this->buildCustomerLoggedIn();
		static::assertTrue($customer->isSafe());
		static::assertTrue($customer->isCommenter());
	}

	public function testLogoutResetsState(): void {
		$customer = $this->buildCustomerLoggedIn();
		static::assertTrue($customer->isLogged());

		$customer->logout();

		static::assertFalse($customer->isLogged());
		static::assertSame(0, $customer->getId());
		static::assertSame('', $customer->getFirstName());
		static::assertSame('', $customer->getEmail());
	}

	public function testGetBalanceReturnsZeroWhenNotLoggedIn(): void {
		$result = DbResultFactory::one(['total' => null]);

		$db = new class ($result) {
			public function __construct(private object $result) {}

			public function query(string $sql): object {
				return $this->result;
			}

			public function escape(string $v): string {
				return addslashes($v);
			}
		};

		$session = new \stdClass();
		$session->data = [];

		$request = new \stdClass();
		$request->server = ['REMOTE_ADDR' => '127.0.0.1'];

		$registry = (new RegistryBuilder())
			->with('db', $db)
			->with('session', $session)
			->with('request', $request)
			->build();

		$customer = new Customer($registry);
		static::assertEqualsWithDelta(0.0, $customer->getBalance(), 0.01);
	}

	public function testGetRewardPointsReturnsZeroWhenNotLoggedIn(): void {
		$result = DbResultFactory::one(['total' => null]);

		$db = new class ($result) {
			public function __construct(private object $result) {}

			public function query(string $sql): object {
				return $this->result;
			}

			public function escape(string $v): string {
				return addslashes($v);
			}
		};

		$session = new \stdClass();
		$session->data = [];

		$request = new \stdClass();
		$request->server = ['REMOTE_ADDR' => '127.0.0.1'];

		$registry = (new RegistryBuilder())
			->with('db', $db)
			->with('session', $session)
			->with('request', $request)
			->build();

		$customer = new Customer($registry);
		static::assertEqualsWithDelta(0.0, $customer->getRewardPoints(), 0.01);
	}

	public function testGetAddressIdReturnsZeroWhenNotLoggedIn(): void {
		$customer = $this->buildCustomerNotLoggedIn();
		static::assertSame(0, $customer->getAddressId());
	}
}
