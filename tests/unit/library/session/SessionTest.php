<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Session;

use PHPUnit\Framework\TestCase;
use Opencart\System\Library\Session;
use Tests\Support\RegistryBuilder;

class SessionTest extends TestCase
{
    private \Opencart\System\Engine\Registry $registry;

    protected function setUp(): void
    {
        $config = new class {
            private array $data = [
                'config_store_id'          => 0,
                'config_language_id'       => 1,
                'config_customer_group_id' => 1,
                'config_weight_class_id'   => 1,
                'config_tax'               => false,
                'session_expire'           => 86400,
                'session_divisor'          => 10,
                'session_probability'      => 1,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $this->registry = (new RegistryBuilder())->with('config', $config)->build();
    }

    // ------------------------------------------------------------------
    // Constructor
    // ------------------------------------------------------------------

    public function testConstructorThrowsOnInvalidAdaptor(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not load session adaptor NonExistentAdaptor session!');

        new Session('NonExistentAdaptor', $this->registry);
    }

    public function testConstructorWithValidAdaptor(): void
    {
        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22');
        $this->assertInstanceOf(Session::class, $session);
    }

    // ------------------------------------------------------------------
    // getId()
    // ------------------------------------------------------------------

    public function testGetIdReturnsSessionId(): void
    {
        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuvwx');
        $this->assertSame('abcdefghijklmnopqrstuvwx', $session->getId());
    }

    // ------------------------------------------------------------------
    // start()
    // ------------------------------------------------------------------

    public function testStartGeneratesIdWhenEmpty(): void
    {
        $session = new Session('DB', $this->registry);
        $id = $session->start();
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{26}$/', $id);
        $this->assertSame($id, $session->getId());
    }

    public function testStartUsesProvidedValidId(): void
    {
        $session = new Session('DB', $this->registry);
        $id = $session->start('aaaaaabbbbbbccccccdddddd');
        $this->assertSame('aaaaaabbbbbbccccccdddddd', $id);
    }

    public function testStartThrowsOnInvalidSessionId(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid session ID');

        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22'); // initialize session_id for shutdown
        $session->start('short');
    }

    public function testStartThrowsOnSessionIdWithInvalidChars(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid session ID');

        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22'); // initialize session_id for shutdown
        $session->start('abc!@#$%^&*()_+=<>?/\\{}[]');
    }

    public function testStartLoadsDataFromAdaptor(): void
    {
        $data = ['user_id' => 42, 'token' => 'xyz'];

        $db = new class($data) {
            private array $data;
            public function __construct(array $data) { $this->data = $data; }
            public function query(string $sql): object {
                $r = new \stdClass();
                $r->num_rows = 1;
                $r->rows = [['data' => json_encode($this->data)]];
                $r->row = ['data' => json_encode($this->data)];
                return $r;
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $config = new class {
            private array $data = [
                'session_expire'      => 86400,
                'session_divisor'     => 10,
                'session_probability' => 1,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $registry = (new RegistryBuilder())->with('db', $db)->with('config', $config)->build();
        $session = new Session('DB', $registry);
        $session->start('abcdefghijklmnopqrstuv22');

        $this->assertSame(42, $session->data['user_id']);
        $this->assertSame('xyz', $session->data['token']);
    }

    public function testStartReturnsEmptyDataWhenNoSessionFound(): void
    {
        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22');

        $this->assertSame([], $session->data);
    }

    // ------------------------------------------------------------------
    // close()
    // ------------------------------------------------------------------

    public function testCloseWritesDataViaAdaptor(): void
    {
        $log = new \ArrayObject();

        $db = new class($log) {
            private \ArrayObject $log;
            public function __construct(\ArrayObject $log) { $this->log = $log; }
            public function query(string $sql): object {
                $this->log[] = $sql;
                $r = new \stdClass();
                $r->num_rows = 0;
                $r->rows = [];
                $r->row = [];
                return $r;
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $config = new class {
            private array $data = [
                'session_expire'      => 86400,
                'session_divisor'     => 10,
                'session_probability' => 1,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $registry = (new RegistryBuilder())->with('db', $db)->with('config', $config)->build();
        $session = new Session('DB', $registry);
        $session->start('abcdefghijklmnopqrstuv22');
        $session->data = ['cart' => [1, 2, 3]];
        $session->close();

        $queries = $log->getArrayCopy();
        $lastQuery = end($queries);
        $this->assertStringContainsString('REPLACE INTO', $lastQuery);
        $this->assertStringContainsString('abcdefghijklmnopqrstuv22', $lastQuery);
    }

    // ------------------------------------------------------------------
    // destroy()
    // ------------------------------------------------------------------

    public function testDestroyClearsDataAndCallsAdaptor(): void
    {
        $log = new \ArrayObject();

        $db = new class($log) {
            private \ArrayObject $log;
            public function __construct(\ArrayObject $log) { $this->log = $log; }
            public function query(string $sql): object {
                $this->log[] = $sql;
                $r = new \stdClass();
                $r->num_rows = 0;
                $r->rows = [];
                $r->row = [];
                return $r;
            }
            public function escape(string $v): string { return addslashes($v); }
        };

        $config = new class {
            private array $data = [
                'session_expire'      => 86400,
                'session_divisor'     => 10,
                'session_probability' => 1,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $registry = (new RegistryBuilder())->with('db', $db)->with('config', $config)->build();
        $session = new Session('DB', $registry);
        $session->start('abcdefghijklmnopqrstuv22');
        $session->data = ['foo' => 'bar'];
        $session->destroy();

        $this->assertSame([], $session->data);
        $queries = $log->getArrayCopy();
        $lastQuery = end($queries);
        $this->assertStringContainsString('DELETE FROM', $lastQuery);
        $this->assertStringContainsString('abcdefghijklmnopqrstuv22', $lastQuery);
    }

    // ------------------------------------------------------------------
    // gc()
    // ------------------------------------------------------------------

    public function testGcDelegatesToAdaptor(): void
    {
        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22');
        $session->gc();
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // Edge cases for start() regex validation
    // ------------------------------------------------------------------

    public function testStartAcceptsMinLength22(): void
    {
        $session = new Session('DB', $this->registry);
        $id = $session->start('abcdefghijklmnopqrstuv');
        $this->assertSame('abcdefghijklmnopqrstuv', $id);
    }

    public function testStartAcceptsMaxLength52(): void
    {
        $session = new Session('DB', $this->registry);
        $id = str_repeat('a', 52);
        $result = $session->start($id);
        $this->assertSame($id, $result);
    }

    public function testStartRejectsLength53(): void
    {
        $this->expectException(\Exception::class);
        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22'); // initialize session_id for shutdown
        $session->start(str_repeat('a', 53));
    }

    public function testStartRejectsLength21(): void
    {
        $this->expectException(\Exception::class);
        $session = new Session('DB', $this->registry);
        $session->start('abcdefghijklmnopqrstuv22'); // initialize session_id for shutdown
        $session->start(str_repeat('a', 21));
    }

    public function testStartAcceptsHyphensAndCommas(): void
    {
        $session = new Session('DB', $this->registry);
        $id = 'abc-def,ghi-jkl,mnopqrstuv';
        $result = $session->start($id);
        $this->assertSame($id, $result);
    }
}
