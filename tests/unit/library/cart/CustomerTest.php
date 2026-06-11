<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Customer;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

// Customer class uses oc_get_ip() and oc_strtolower() helper functions.
require_once DIR_SYSTEM . 'helper/general.php';

class CustomerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    private function customerRow(): array
    {
        return [
            'customer_id'       => 42,
            'firstname'         => 'John',
            'lastname'          => 'Doe',
            'customer_group_id' => 1,
            'email'             => 'john@example.com',
            'telephone'         => '555-1234',
            'newsletter'        => true,
            'safe'              => true,
            'commenter'         => true,
            'password'          => password_hash('secret', PASSWORD_DEFAULT),
            'salt'              => '',
        ];
    }

    /**
     * Build a Customer with a DB mock controlled by a call counter.
     *
     * @param array $sessionData  Session data (e.g. ['customer_id' => 42])
     * @param array $queryResults Ordered list of DB result objects
     */
    private function buildCustomer(array $sessionData = [], array $queryResults = []): Customer
    {
        $idx = 0;
        $db = new class($idx, $queryResults) {
            private int $idx;
            private array $results;
            public function __construct(int &$idx, array $results) {
                $this->idx = &$idx;
                $this->results = $results;
            }
            public function query(string $sql): object {
                return $this->results[$this->idx++] ?? DbResultFactory::empty();
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $session = new class($sessionData) {
            public array $data;
            public function __construct(array $data) { $this->data = $data; }
            public function getId(): string { return 'test-session'; }
        };

        $request = new class {
            public array $server = ['REMOTE_ADDR' => '127.0.0.1'];
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $request)
            ->build();

        return new Customer($registry);
    }

    // --- Constructor: no session customer ---

    public function testConstructorWithNoSessionCustomerDefaults(): void
    {
        $customer = $this->buildCustomer();

        $this->assertFalse($customer->isLogged());
        $this->assertSame(0, $customer->getId());
        $this->assertSame('', $customer->getFirstName());
        $this->assertSame('', $customer->getLastName());
        $this->assertSame(0, $customer->getGroupId());
        $this->assertSame('', $customer->getEmail());
        $this->assertSame('', $customer->getTelephone());
        $this->assertFalse($customer->getNewsletter());
        $this->assertFalse($customer->isSafe());
        $this->assertFalse($customer->isCommenter());
    }

    // --- Constructor: session customer found ---

    public function testConstructorWithValidSessionLoadsCustomer(): void
    {
        $row = $this->customerRow();
        $customer = $this->buildCustomer(
            ['customer_id' => 42],
            [
                DbResultFactory::one($row), // SELECT customer
                // UPDATE query returns empty
            ]
        );

        $this->assertTrue($customer->isLogged());
        $this->assertSame(42, $customer->getId());
        $this->assertSame('John', $customer->getFirstName());
        $this->assertSame('Doe', $customer->getLastName());
        $this->assertSame(1, $customer->getGroupId());
        $this->assertSame('john@example.com', $customer->getEmail());
        $this->assertSame('555-1234', $customer->getTelephone());
        $this->assertTrue($customer->getNewsletter());
        $this->assertTrue($customer->isSafe());
        $this->assertTrue($customer->isCommenter());
    }

    // --- Constructor: session customer not found (triggers logout) ---

    public function testConstructorWithInvalidSessionCallsLogout(): void
    {
        $customer = $this->buildCustomer(
            ['customer_id' => 999],
            [DbResultFactory::empty()] // No matching customer
        );

        $this->assertFalse($customer->isLogged());
        $this->assertSame(0, $customer->getId());
    }

    // --- login() ---

    public function testLoginWithCorrectPasswordReturnsTrue(): void
    {
        $row = $this->customerRow();
        $customer = $this->buildCustomer([], [
            DbResultFactory::one($row), // login query
            // UPDATE for password rehash (if needed)
            // UPDATE for ip
        ]);

        $result = $customer->login('john@example.com', 'secret');
        $this->assertTrue($result);
        $this->assertTrue($customer->isLogged());
        $this->assertSame(42, $customer->getId());
    }

    public function testLoginWithWrongPasswordReturnsFalse(): void
    {
        $row = $this->customerRow();
        $customer = $this->buildCustomer([], [
            DbResultFactory::one($row),
        ]);

        $result = $customer->login('john@example.com', 'wrong_password');
        $this->assertFalse($result);
        $this->assertFalse($customer->isLogged());
    }

    public function testLoginWithNoMatchingEmailReturnsFalse(): void
    {
        $customer = $this->buildCustomer([], [
            DbResultFactory::empty(),
        ]);

        $result = $customer->login('nobody@example.com', 'secret');
        $this->assertFalse($result);
    }

    public function testLoginWithOverrideSkipsPasswordCheck(): void
    {
        $row = $this->customerRow();
        $customer = $this->buildCustomer([], [
            DbResultFactory::one($row),
        ]);

        $result = $customer->login('john@example.com', '', true);
        $this->assertTrue($result);
        $this->assertTrue($customer->isLogged());
    }

    public function testLoginWithLegacySaltPassword(): void
    {
        $password = 'legacy_pass';
        $salt = 'somesalt';
        $hashedPassword = sha1($salt . sha1($salt . sha1($password)));

        $row = $this->customerRow();
        $row['password'] = $hashedPassword;
        $row['salt'] = $salt;

        $customer = $this->buildCustomer([], [
            DbResultFactory::one($row),
        ]);

        $result = $customer->login('john@example.com', $password);
        $this->assertTrue($result);
    }

    public function testLoginWithLegacyMd5Password(): void
    {
        $password = 'md5_pass';
        $row = $this->customerRow();
        $row['password'] = md5($password);
        $row['salt'] = '';

        $customer = $this->buildCustomer([], [
            DbResultFactory::one($row),
        ]);

        $result = $customer->login('john@example.com', $password);
        $this->assertTrue($result);
    }

    // --- logout() ---

    public function testLogoutResetsAllFields(): void
    {
        $row = $this->customerRow();
        $customer = $this->buildCustomer([], [
            DbResultFactory::one($row),
        ]);

        $customer->login('john@example.com', 'secret');
        $this->assertTrue($customer->isLogged());

        $customer->logout();

        $this->assertFalse($customer->isLogged());
        $this->assertSame(0, $customer->getId());
        $this->assertSame('', $customer->getFirstName());
        $this->assertSame('', $customer->getLastName());
        $this->assertSame(0, $customer->getGroupId());
        $this->assertSame('', $customer->getEmail());
        $this->assertSame('', $customer->getTelephone());
        $this->assertFalse($customer->getNewsletter());
        $this->assertFalse($customer->isSafe());
        $this->assertFalse($customer->isCommenter());
    }

    // --- getAddressId() ---

    public function testGetAddressIdReturnsIdWhenFound(): void
    {
        $row = $this->customerRow();
        $idx = 0;
        $results = [
            DbResultFactory::one($row),   // login
            DbResultFactory::empty(),      // UPDATE ip
            DbResultFactory::one(['address_id' => 5]), // getAddressId
        ];
        $db = new class($idx, $results) {
            private int $idx;
            private array $results;
            public function __construct(int &$idx, array $results) {
                $this->idx = &$idx;
                $this->results = $results;
            }
            public function query(string $sql): object {
                return $this->results[$this->idx++] ?? DbResultFactory::empty();
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $session = new class { public array $data = []; public function getId(): string { return 's'; } };
        $request = new class { public array $server = ['REMOTE_ADDR' => '127.0.0.1']; };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $request)
            ->build();

        $customer = new Customer($registry);
        $customer->login('john@example.com', 'secret');

        $this->assertSame(5, $customer->getAddressId());
    }

    public function testGetAddressIdReturnsZeroWhenNotFound(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame(0, $customer->getAddressId());
    }

    // --- getBalance() ---

    public function testGetBalanceReturnsTotal(): void
    {
        $idx = 0;
        $results = [
            DbResultFactory::one(['total' => '150.50']),
        ];
        $db = new class($idx, $results) {
            private int $idx;
            private array $results;
            public function __construct(int &$idx, array $results) {
                $this->idx = &$idx;
                $this->results = $results;
            }
            public function query(string $sql): object {
                return $this->results[$this->idx++] ?? DbResultFactory::empty();
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $session = new class { public array $data = []; public function getId(): string { return 's'; } };
        $request = new class { public array $server = ['REMOTE_ADDR' => '127.0.0.1']; };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $request)
            ->build();

        $customer = new Customer($registry);
        $this->assertEqualsWithDelta(150.50, $customer->getBalance(), 0.001);
    }

    // --- getRewardPoints() ---

    public function testGetRewardPointsReturnsTotal(): void
    {
        $idx = 0;
        $results = [
            DbResultFactory::one(['total' => '250']),
        ];
        $db = new class($idx, $results) {
            private int $idx;
            private array $results;
            public function __construct(int &$idx, array $results) {
                $this->idx = &$idx;
                $this->results = $results;
            }
            public function query(string $sql): object {
                return $this->results[$this->idx++] ?? DbResultFactory::empty();
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $session = new class { public array $data = []; public function getId(): string { return 's'; } };
        $request = new class { public array $server = ['REMOTE_ADDR' => '127.0.0.1']; };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $request)
            ->build();

        $customer = new Customer($registry);
        $this->assertEqualsWithDelta(250.0, $customer->getRewardPoints(), 0.001);
    }
}
