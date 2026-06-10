<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\Customer;
use Tests\Support\RegistryBuilder;
use Tests\Support\DbResultFactory;

class CustomerTest extends TestCase
{
    protected function setUp(): void
    {
        // Load helper functions required by Customer (oc_get_ip, oc_strtolower)
        require_once DIR_SYSTEM . 'helper/general.php';

        // oc_get_ip() reads $_SERVER['REMOTE_ADDR'] directly
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    private function buildCustomer(object $dbResult = null, array $sessionData = []): Customer
    {
        $result = $dbResult ?? DbResultFactory::empty();

        $db = new class($result) {
            public function __construct(private object $r) {}
            public function query(string $sql): object { return $this->r; }
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

    public function testIsLoggedReturnsFalseWhenNoSession(): void
    {
        $customer = $this->buildCustomer();
        $this->assertFalse($customer->isLogged());
    }

    public function testGetIdReturnsZeroWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame(0, $customer->getId());
    }

    public function testGetGroupIdReturnsZeroWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame(0, $customer->getGroupId());
    }

    public function testGetEmailReturnsEmptyWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame('', $customer->getEmail());
    }

    public function testGetFirstNameReturnsEmptyWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame('', $customer->getFirstName());
    }

    public function testGetLastNameReturnsEmptyWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame('', $customer->getLastName());
    }

    public function testGetTelephoneReturnsEmptyWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame('', $customer->getTelephone());
    }

    public function testGetNewsletterReturnsFalseWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertFalse($customer->getNewsletter());
    }

    public function testIsSafeReturnsFalseWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertFalse($customer->isSafe());
    }

    public function testIsCommenterReturnsFalseWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertFalse($customer->isCommenter());
    }

    public function testIsLoggedReturnsTrueWhenSessionHasCustomer(): void
    {
        $row = [
            'customer_id'       => 42,
            'firstname'         => 'John',
            'lastname'          => 'Doe',
            'customer_group_id' => 1,
            'email'             => 'john@example.com',
            'telephone'         => '555-1234',
            'newsletter'        => true,
            'safe'              => 1,
            'commenter'         => 0,
        ];

        $customer = $this->buildCustomer(
            DbResultFactory::one($row),
            ['customer_id' => 42]
        );

        $this->assertTrue($customer->isLogged());
        $this->assertSame(42, $customer->getId());
        $this->assertSame('John', $customer->getFirstName());
        $this->assertSame('Doe', $customer->getLastName());
        $this->assertSame(1, $customer->getGroupId());
        $this->assertSame('john@example.com', $customer->getEmail());
        $this->assertSame('555-1234', $customer->getTelephone());
        $this->assertTrue($customer->isSafe());
        $this->assertFalse($customer->isCommenter());
    }

    public function testLogoutClearsCustomerData(): void
    {
        $row = [
            'customer_id'       => 42,
            'firstname'         => 'John',
            'lastname'          => 'Doe',
            'customer_group_id' => 1,
            'email'             => 'john@example.com',
            'telephone'         => '555-1234',
            'newsletter'        => false,
            'safe'              => 0,
            'commenter'         => 0,
        ];

        $customer = $this->buildCustomer(
            DbResultFactory::one($row),
            ['customer_id' => 42]
        );

        $customer->logout();
        $this->assertFalse($customer->isLogged());
        $this->assertSame(0, $customer->getId());
        $this->assertSame('', $customer->getEmail());
    }

    public function testGetAddressIdReturnsZeroWhenNotLogged(): void
    {
        $customer = $this->buildCustomer();
        $this->assertSame(0, $customer->getAddressId());
    }

    public function testGetBalanceReturnsZeroWhenNotLogged(): void
    {
        $customer = $this->buildCustomer(DbResultFactory::one(['total' => null]));
        $this->assertSame(0.0, $customer->getBalance());
    }

    public function testGetRewardPointsReturnsZeroWhenNotLogged(): void
    {
        $customer = $this->buildCustomer(DbResultFactory::one(['total' => null]));
        $this->assertSame(0.0, $customer->getRewardPoints());
    }

    public function testLoginReturnsFalseForUnknownEmail(): void
    {
        $customer = $this->buildCustomer(DbResultFactory::empty());
        $this->assertFalse($customer->login('unknown@example.com', 'pass'));
    }

    public function testLoginReturnsFalseForWrongPassword(): void
    {
        $row = [
            'customer_id'       => 1,
            'firstname'         => 'Jane',
            'lastname'          => 'Doe',
            'customer_group_id' => 1,
            'email'             => 'jane@example.com',
            'telephone'         => '555-0000',
            'newsletter'        => false,
            'safe'              => 0,
            'commenter'         => 0,
            'password'          => password_hash('correct', PASSWORD_DEFAULT),
            'status'            => 1,
        ];

        $customer = $this->buildCustomer(DbResultFactory::one($row));
        $this->assertFalse($customer->login('jane@example.com', 'wrong'));
    }

    public function testLoginReturnsTrueForCorrectPassword(): void
    {
        $row = [
            'customer_id'       => 5,
            'firstname'         => 'Alice',
            'lastname'          => 'Smith',
            'customer_group_id' => 2,
            'email'             => 'alice@example.com',
            'telephone'         => '555-9999',
            'newsletter'        => true,
            'safe'              => 1,
            'commenter'         => 1,
            'password'          => password_hash('secret', PASSWORD_DEFAULT),
            'status'            => 1,
        ];

        $session = new class {
            public array $data = [];
            public function getId(): string { return 'test-session'; }
        };

        $db = new class($row) {
            private array $row;
            public function __construct(array $row) { $this->row = $row; }
            public function query(string $sql): object {
                $r = new \stdClass();
                $r->rows = [$this->row];
                $r->row = $this->row;
                $r->num_rows = 1;
                return $r;
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $request = new class {
            public array $server = ['REMOTE_ADDR' => '127.0.0.1'];
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $request)
            ->build();

        $customer = new Customer($registry);
        $result = $customer->login('alice@example.com', 'secret');

        $this->assertTrue($result);
        $this->assertTrue($customer->isLogged());
        $this->assertSame(5, $customer->getId());
        $this->assertSame('alice@example.com', $customer->getEmail());
    }

    public function testLoginWithOverrideSkipsPasswordCheck(): void
    {
        $row = [
            'customer_id'       => 7,
            'firstname'         => 'Bob',
            'lastname'          => 'Jones',
            'customer_group_id' => 1,
            'email'             => 'bob@example.com',
            'telephone'         => '555-1111',
            'newsletter'        => false,
            'safe'              => 0,
            'commenter'         => 0,
            'password'          => password_hash('irrelevant', PASSWORD_DEFAULT),
            'status'            => 1,
        ];

        $session = new class {
            public array $data = [];
            public function getId(): string { return 'test-session'; }
        };

        $db = new class($row) {
            private array $row;
            public function __construct(array $row) { $this->row = $row; }
            public function query(string $sql): object {
                $r = new \stdClass();
                $r->rows = [$this->row];
                $r->row = $this->row;
                $r->num_rows = 1;
                return $r;
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $request = new class {
            public array $server = ['REMOTE_ADDR' => '127.0.0.1'];
        };

        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $request)
            ->build();

        $customer = new Customer($registry);
        $result = $customer->login('bob@example.com', 'any-pass', true);

        $this->assertTrue($result);
        $this->assertSame(7, $customer->getId());
    }
}
