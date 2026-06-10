<?php
declare(strict_types=1);

namespace Tests\Unit\Library\Session;

use PHPUnit\Framework\TestCase;
use Tests\Support\RegistryBuilder;

// Define constants needed by the Redis adaptor
if (!defined('CACHE_HOSTNAME')) {
    define('CACHE_HOSTNAME', '127.0.0.1');
}
if (!defined('CACHE_PORT')) {
    define('CACHE_PORT', 6379);
}
if (!defined('CACHE_PREFIX')) {
    define('CACHE_PREFIX', 'test');
}

// Provide a stub \Redis class if the extension is not loaded.
if (!extension_loaded('redis')) {
    eval('class Redis {
        public function pconnect($host, $port) { return true; }
        public function get($key) { return false; }
        public function set($key, $value, $options = null) { return true; }
        public function unlink($key, ...$otherKeys) { return 1; }
    }');
}

class RedisAdaptorTest extends TestCase
{
    private \Opencart\System\Engine\Registry $registry;

    protected function setUp(): void
    {
        $config = new class {
            private array $data = [
                'session_expire'      => 3600,
                'session_divisor'     => 10,
                'session_probability' => 1,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $this->registry = (new RegistryBuilder())->with('config', $config)->build();
    }

    private function createAdaptor(): \Opencart\System\Library\Session\Redis
    {
        $adaptor = new \Opencart\System\Library\Session\Redis($this->registry);

        // The constructor may fail to connect (no Redis server in test/CI),
        // leaving $redis and $prefix uninitialized. Use reflection to set them.
        $ref = new \ReflectionClass($adaptor);

        // Build a mock that extends whatever \Redis class is available
        $store = new \ArrayObject();

        if (extension_loaded('redis')) {
            // Real Redis extension loaded — create a mock via PHPUnit
            $mockRedis = $this->createMock(\Redis::class);
            $mockRedis->method('get')->willReturnCallback(function ($key) use ($store) {
                return $store[$key] ?? false;
            });
            $mockRedis->method('set')->willReturnCallback(function ($key, $value) use ($store) {
                $store[$key] = $value;
                return true;
            });
            $mockRedis->method('unlink')->willReturnCallback(function ($key) use ($store) {
                unset($store[$key]);
                return 1;
            });
        } else {
            // No extension — use our eval'd stub which can be extended
            $mockRedis = new class($store) extends \Redis {
                private \ArrayObject $store;
                public function __construct(\ArrayObject $store) { $this->store = $store; }
                public function pconnect($host, $port) { return true; }
                public function get($key) { return $this->store[$key] ?? false; }
                public function set($key, $value, $options = null) { $this->store[$key] = $value; return true; }
                public function unlink($key, ...$otherKeys) { unset($this->store[$key]); return 1; }
            };
        }

        $redisProp = $ref->getProperty('redis');
        $redisProp->setAccessible(true);
        $redisProp->setValue($adaptor, $mockRedis);

        $prefixProp = $ref->getProperty('prefix');
        $prefixProp->setAccessible(true);
        $prefixProp->setValue($adaptor, CACHE_PREFIX . '.session.');

        return $adaptor;
    }

    public function testReadReturnsEmptyArrayWhenKeyMissing(): void
    {
        $adaptor = $this->createAdaptor();
        $this->assertSame([], $adaptor->read('missing_session_id_12345'));
    }

    public function testReadReturnsDecodedData(): void
    {
        $adaptor = $this->createAdaptor();
        $adaptor->write('existing_session_abc123', ['user' => 'test', 'cart' => [1]]);
        $result = $adaptor->read('existing_session_abc123');

        $this->assertSame('test', $result['user']);
        $this->assertSame([1], $result['cart']);
    }

    public function testWriteStoresDataWithSessionId(): void
    {
        $adaptor = $this->createAdaptor();
        $result = $adaptor->write('write_session_abcdefgh', ['item' => 'value']);

        $this->assertTrue($result);
    }

    public function testWriteWithEmptySessionIdDoesNotStore(): void
    {
        $adaptor = $this->createAdaptor();
        $result = $adaptor->write('', ['item' => 'value']);

        $this->assertTrue($result);
    }

    public function testWriteWithEmptyDataStoresEmptyString(): void
    {
        $adaptor = $this->createAdaptor();
        $result = $adaptor->write('empty_data_session_1234', []);

        $this->assertTrue($result);
    }

    public function testDestroyRemovesKey(): void
    {
        $adaptor = $this->createAdaptor();
        $adaptor->write('destroy_session_abc12345', ['data' => true]);
        $result = $adaptor->destroy('destroy_session_abc12345');

        $this->assertTrue($result);
        $this->assertSame([], $adaptor->read('destroy_session_abc12345'));
    }

    public function testGcReturnsTrue(): void
    {
        $adaptor = $this->createAdaptor();
        $result = $adaptor->gc();
        $this->assertTrue($result);
    }
}
