<?php
declare(strict_types=1);

namespace Tests\Support;

use Opencart\System\Engine\Registry;

final class RegistryBuilder
{
    private array $overrides = [];

    public function with(string $key, object $value): static
    {
        $this->overrides[$key] = $value;
        return $this;
    }

    public function build(): Registry
    {
        $emptyResult = DbResultFactory::empty();

        $db = new class($emptyResult) {
            public function __construct(private object $result) {}
            public function query(string $sql): object { return $this->result; }
            public function escape(string $v): string  { return addslashes($v); }
        };

        $config = new class {
            private array $data = [
                'config_store_id'          => 0,
                'config_language_id'       => 1,
                'config_customer_group_id' => 1,
                'config_weight_class_id'   => 1,
                'config_tax'               => false,
                'session_expire'           => 86400,
            ];
            public function get(string $key): mixed { return $this->data[$key] ?? null; }
            public function set(string $key, mixed $v): void { $this->data[$key] = $v; }
        };

        $registry = new Registry();
        $registry->set('db',       $db);
        $registry->set('config',   $config);
        $registry->set('customer', new class { public function isLogged(): bool { return false; } public function getId(): int { return 0; } });
        $registry->set('session',  new class { public function getId(): string { return 'test-session'; } });
        $registry->set('tax',      new class { public function getRates(float $p, int $id): array { return []; } public function calculate(float $p, int $id, bool $a): float { return $p; } });
        $registry->set('weight',   new class { public function convert(float $v, int $f, int $t): float { return $v; } });

        foreach ($this->overrides as $key => $value) {
            $registry->set($key, $value);
        }

        return $registry;
    }
}
