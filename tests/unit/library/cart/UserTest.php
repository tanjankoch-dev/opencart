<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Cart;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Cart\User;
use Tests\Support\DbResultFactory;
use Tests\Support\RegistryBuilder;

// Ensure the helper containing oc_get_ip() is loaded.
require_once DIR_SYSTEM . 'helper/general.php';

class UserTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    // ------------------------------------------------------------------
    //  Helper: build a session stub with a public `data` array
    // ------------------------------------------------------------------
    private function makeSession(array $data = []): object
    {
        return new class($data) {
            public array $data;
            public function __construct(array $data) { $this->data = $data; }
            public function getId(): string { return 'test-session'; }
        };
    }

    private function makeRequest(): object
    {
        return new class {
            public object $server;
            public function __construct()
            {
                $this->server = new \stdClass();
                $this->server->data = ['REMOTE_ADDR' => '127.0.0.1'];
            }
        };
    }

    // ------------------------------------------------------------------
    //  Helper: standard user row returned by SELECT * FROM oc_user
    // ------------------------------------------------------------------
    private function userRow(array $overrides = []): array
    {
        return array_merge([
            'user_id'       => 1,
            'username'      => 'admin',
            'firstname'     => 'John',
            'lastname'      => 'Doe',
            'email'         => 'admin@example.com',
            'user_group_id' => 1,
            'password'      => password_hash('secret', PASSWORD_DEFAULT),
            'salt'          => '',
            'status'        => '1',
        ], $overrides);
    }

    private function permissionRow(array $perms = ['access' => ['sale/order'], 'modify' => ['sale/order']]): array
    {
        return ['permission' => json_encode($perms)];
    }

    // ------------------------------------------------------------------
    //  Helper: build a DB mock with a call counter
    // ------------------------------------------------------------------
    private function makeDb(array $responses): object
    {
        return new class($responses) {
            private int $callIndex = 0;
            private array $responses;
            public function __construct(array $responses) { $this->responses = $responses; }
            public function query(string $sql): object
            {
                return $this->responses[$this->callIndex++] ?? DbResultFactory::empty();
            }
            public function escape(string $v): string { return addslashes($v); }
        };
    }

    // ------------------------------------------------------------------
    //  Constructor tests
    // ------------------------------------------------------------------

    public function testConstructorWithNoSessionUser(): void
    {
        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);

        $this->assertSame(0, $user->getId());
        $this->assertSame('', $user->getUserName());
        $this->assertFalse($user->isLogged());
    }

    public function testConstructorWithValidSessionUser(): void
    {
        $userRow = $this->userRow();
        $permRow = $this->permissionRow();

        // Query sequence: 1) SELECT user, 2) UPDATE ip, 3) SELECT user_group
        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),  // UPDATE returns empty
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession(['user_id' => 1]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);

        $this->assertTrue($user->isLogged());
        $this->assertSame(1, $user->getId());
        $this->assertSame('admin', $user->getUserName());
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertSame('admin@example.com', $user->getEmail());
        $this->assertSame(1, $user->getGroupId());
        $this->assertTrue($user->hasPermission('access', 'sale/order'));
        $this->assertTrue($user->hasPermission('modify', 'sale/order'));
    }

    public function testConstructorWithInvalidSessionUserCallsLogout(): void
    {
        // User not found (num_rows = 0) → logout path
        $db = $this->makeDb([
            DbResultFactory::empty(),  // SELECT user → not found
        ]);

        $session = $this->makeSession(['user_id' => 999]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);

        $this->assertFalse($user->isLogged());
        $this->assertSame(0, $user->getId());
        // Session user_id should be unset by logout
        $this->assertArrayNotHasKey('user_id', $session->data);
    }

    public function testConstructorWithInvalidPermissionsJson(): void
    {
        $userRow = $this->userRow();
        // Invalid JSON for permissions
        $permRow = ['permission' => 'not-valid-json'];

        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),  // UPDATE
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession(['user_id' => 1]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);

        $this->assertTrue($user->isLogged());
        // Permissions should be empty since JSON was invalid
        $this->assertFalse($user->hasPermission('access', 'sale/order'));
    }

    // ------------------------------------------------------------------
    //  login() tests
    // ------------------------------------------------------------------

    public function testLoginSuccessWithPasswordVerify(): void
    {
        $password = 'secret123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userRow = $this->userRow(['password' => $hash]);
        $permRow = $this->permissionRow();

        // Constructor fires no queries (no session user_id).
        // login(): 1) SELECT user, 2) possible UPDATE (rehash), 3) SELECT user_group
        $db = $this->makeDb([
            DbResultFactory::one($userRow),  // login SELECT user
            DbResultFactory::one($permRow),  // SELECT user_group (no rehash for fresh hash)
        ]);

        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $result = $user->login('admin', $password);

        $this->assertTrue($result);
        $this->assertTrue($user->isLogged());
        $this->assertSame(1, $user->getId());
        $this->assertSame('admin', $user->getUserName());
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertSame('admin@example.com', $user->getEmail());
        $this->assertSame(1, $user->getGroupId());
        $this->assertSame(1, $session->data['user_id']);
    }

    public function testLoginFailsUserNotFound(): void
    {
        $db = $this->makeDb([
            DbResultFactory::empty(),  // login SELECT → no user
        ]);

        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $result = $user->login('nobody', 'wrong');

        $this->assertFalse($result);
        $this->assertFalse($user->isLogged());
    }

    public function testLoginFailsWrongPassword(): void
    {
        $userRow = $this->userRow([
            'password' => password_hash('correct', PASSWORD_DEFAULT),
            'salt'     => '',
        ]);

        $db = $this->makeDb([
            DbResultFactory::one($userRow),  // login SELECT → user found
        ]);

        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $result = $user->login('admin', 'wrongpassword');

        $this->assertFalse($result);
    }

    public function testLoginWithSaltBasedPassword(): void
    {
        $password = 'legacy';
        $salt = 'randomsalt';
        $legacyHash = sha1($salt . sha1($salt . sha1($password)));
        $userRow = $this->userRow([
            'password' => $legacyHash,
            'salt'     => $salt,
        ]);
        $permRow = $this->permissionRow();

        // login: SELECT user, UPDATE password (rehash), SELECT user_group
        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),       // UPDATE rehash
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $result = $user->login('admin', $password);

        $this->assertTrue($result);
        $this->assertTrue($user->isLogged());
    }

    public function testLoginWithMd5Password(): void
    {
        $password = 'md5pass';
        $userRow = $this->userRow([
            'password' => md5($password),
            'salt'     => 'something',  // salt is set but hash doesn't match salt path
        ]);
        $permRow = $this->permissionRow();

        // login: SELECT user, UPDATE password (rehash), SELECT user_group
        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),       // UPDATE rehash
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $result = $user->login('admin', $password);

        $this->assertTrue($result);
        $this->assertTrue($user->isLogged());
    }

    public function testLoginWithNonArrayPermissions(): void
    {
        $password = 'secret';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userRow = $this->userRow(['password' => $hash]);

        // Permissions JSON that decodes to a non-array (string)
        $db = $this->makeDb([
            DbResultFactory::one($userRow),                          // login SELECT user
            DbResultFactory::one(['permission' => '"just-a-string"']), // SELECT user_group
        ]);

        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $result = $user->login('admin', $password);

        $this->assertTrue($result);
        $this->assertFalse($user->hasPermission('access', 'anything'));
    }

    // ------------------------------------------------------------------
    //  logout() tests
    // ------------------------------------------------------------------

    public function testLogoutResetsState(): void
    {
        $userRow = $this->userRow();
        $permRow = $this->permissionRow();

        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession(['user_id' => 1]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $this->assertTrue($user->isLogged());

        $user->logout();

        $this->assertFalse($user->isLogged());
        $this->assertSame(0, $user->getId());
        $this->assertSame('', $user->getUserName());
        $this->assertSame('', $user->getFirstName());
        $this->assertSame('', $user->getLastName());
        $this->assertSame('', $user->getEmail());
        $this->assertSame(0, $user->getGroupId());
        $this->assertArrayNotHasKey('user_id', $session->data);
    }

    // ------------------------------------------------------------------
    //  hasPermission() tests
    // ------------------------------------------------------------------

    public function testHasPermissionReturnsFalseForMissingKey(): void
    {
        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $this->assertFalse($user->hasPermission('access', 'sale/order'));
    }

    public function testHasPermissionReturnsFalseForMissingValue(): void
    {
        $userRow = $this->userRow();
        $permRow = $this->permissionRow(['access' => ['catalog/product']]);

        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession(['user_id' => 1]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $this->assertFalse($user->hasPermission('access', 'sale/order'));
    }

    // ------------------------------------------------------------------
    //  isLogged() tests
    // ------------------------------------------------------------------

    public function testIsLoggedReturnsFalseByDefault(): void
    {
        $session = $this->makeSession([]);
        $registry = (new RegistryBuilder())
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);
        $this->assertFalse($user->isLogged());
    }

    // ------------------------------------------------------------------
    //  Getter tests (on a logged-in user)
    // ------------------------------------------------------------------

    public function testGettersReturnCorrectValues(): void
    {
        $userRow = $this->userRow([
            'user_id'       => 42,
            'username'      => 'testuser',
            'firstname'     => 'Jane',
            'lastname'      => 'Smith',
            'email'         => 'jane@example.com',
            'user_group_id' => 5,
        ]);
        $permRow = $this->permissionRow();

        $db = $this->makeDb([
            DbResultFactory::one($userRow),
            DbResultFactory::empty(),
            DbResultFactory::one($permRow),
        ]);

        $session = $this->makeSession(['user_id' => 42]);
        $registry = (new RegistryBuilder())
            ->with('db', $db)
            ->with('session', $session)
            ->with('request', $this->makeRequest())
            ->build();

        $user = new User($registry);

        $this->assertSame(42, $user->getId());
        $this->assertSame('testuser', $user->getUserName());
        $this->assertSame('Jane', $user->getFirstName());
        $this->assertSame('Smith', $user->getLastName());
        $this->assertSame('jane@example.com', $user->getEmail());
        $this->assertSame(5, $user->getGroupId());
    }
}
