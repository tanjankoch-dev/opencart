# Test Coverage Playbook

This document describes the repeatable process for raising unit test coverage
for any OpenCart module to at least **85% line coverage**.

---

## Constraints

- **Do not modify application code** (anything under `upload/`) unless a method
  is genuinely untestable (e.g. it calls `exit()` or relies on global state that
  cannot be injected). If you must change application code, flag every modified
  file in the PR description with a written justification.
- **One PR per module.** Branch name: `test-coverage/<module-name>`.
- The PR description must include a before/after coverage table. This is
  generated automatically by the `coverage-pr.yml` workflow.
- **The PR is only opened when the suite is green and coverage ≥ 85%.**
  The workflow enforces this gate; you cannot bypass it by pushing directly.

---

## Prerequisites

Install a coverage driver. PCOV is the fastest option:

```bash
pecl install pcov
```

The CI workflow (`coverage-pr.yml`) installs PCOV automatically via
`shivammathur/setup-php`.

---

## Step 1 — Measure current coverage

```bash
upload/system/storage/vendor/bin/phpunit \
  --coverage-html coverage/html \
  --coverage-clover coverage/clover.xml
```

Open `coverage/html/index.html`. The dashboard shows per-file line coverage.
Identify every file in your target module that is below 85%.

---

## Step 2 — Classify each class by testability

| Category | Examples | Strategy |
|---|---|---|
| **Pure** — no I/O, no Registry | `Registry`, `Action`, `Config`, `Event` | Instantiate directly, assert outputs |
| **Registry-dependent** | `Tax`, `Weight`, `Currency`, `Cart` | Use `RegistryBuilder` to inject stubs |
| **DB-heavy** — constructor fires SQL | `Cart`, `Weight`, `Currency` | Mock `db->query()` via `RegistryBuilder` |

---

## Step 3 — Use the shared test infrastructure

### `Tests\Support\DbResultFactory`

Produces the `stdClass` objects that `db->query()` returns:

```php
DbResultFactory::empty()          // num_rows=0, rows=[], row=[]
DbResultFactory::one($rowArray)   // num_rows=1
DbResultFactory::many($rowsArray) // num_rows=count($rows)
```

### `Tests\Support\RegistryBuilder`

Wires up a `Registry` with default stubs for `db`, `config`, `customer`,
`session`, `tax`, and `weight`. Override any stub per test:

```php
$registry = (new RegistryBuilder())
    ->with('db', $myCustomDbMock)
    ->build();
```

---

## Step 4 — Module-by-module strategy

### `upload/system/engine/`

All engine classes are pure. One test per public method plus one per branch:

- `Registry`: `get`, `set`, `has`, `unset`, magic `__get`/`__set`/`__isset`
- `Config`: `load` (file exists / file missing), `get`, `set`, `has`
- `Action`: constructor with and without `.` in route; `execute` with magic
  method blocked, controller not found, and callable
- `Event`: `register`, `trigger` (exact match, wildcard, no match), `unregister`

### `upload/system/library/cart/weight.php`

Mock `db->query()` to return two weight-class rows (e.g. `kg=1.0`, `g=0.001`).
Then test:

- `convert($v, $from, $to)` where `$from == $to` (identity path)
- `convert($v, $from, $to)` where both classes exist
- `convert($v, $from, $to)` where one class is missing (falls back to `1`)
- `format()` with a known and unknown class
- `getUnit()` with a known and unknown class

### `upload/system/library/cart/tax.php`

`calculate()`, `getRates()`, and `getTax()` are pure once `$this->tax_rates` is
populated. Populate it by mocking `db->query()` to return tax-rate rows from
`setShippingAddress` / `setPaymentAddress`. Cover both `'F'` (fixed) and `'P'`
(percentage) rate types.

### `upload/system/library/cart/cart.php`

`Cart` is the most complex. `getProducts()` has ~190 lines with branches for
options (select/radio/checkbox/text), subscriptions, discounts (F/P/S),
stock, minimum quantity, reward points, and downloads.

To reach 85% you need at least these scenarios:

1. **Empty cart** (already in `CartTest`) — covers constructor + all
   aggregation methods on empty `$data`.
2. **One product, no options, in stock** — mock `db->query()` to return a cart
   row, a product row, and empty results for everything else.
3. **Out-of-stock product** — product row has `quantity = 0`.
4. **Logged-in customer** — `customer->isLogged()` returns `true`, covering
   the two UPDATE queries in the constructor.

Use a call counter to return different results per query:

```php
$calls = 0;
$db = new class($calls, $cartRow, $productRow) {
    public function __construct(
        private int &$calls,
        private array $cartRow,
        private array $productRow
    ) {}
    public function query(string $sql): object {
        $this->calls++;
        return match($this->calls) {
            1 => DbResultFactory::empty(),                 // DELETE (constructor)
            2 => DbResultFactory::many([$this->cartRow]), // SELECT cart rows
            3 => DbResultFactory::one($this->productRow), // SELECT product
            default => DbResultFactory::empty(),
        };
    }
    public function escape(string $v): string { return addslashes($v); }
};
```

---

## Step 5 — Enforce the threshold in CI

The `coverage-pr.yml` workflow enforces 85% automatically. For local
enforcement during development:

```bash
php tools/coverage/measure.php coverage/clover.xml
# outputs: {"covered":412,"total":480,"percentage":85.8}
```

---

## Step 6 — Iteration loop

Repeat until the module is green:

1. Run PHPUnit with `--coverage-html`
2. Open `coverage/html/<ClassName>.php.html`
3. Red lines = uncovered branches
4. Write the smallest test that turns those lines green
5. Re-run; check the per-file percentage
6. Commit when the file hits ≥ 85%

---

## Step 7 — Open the PR

Push your branch (`test-coverage/<module>`). The `coverage-pr.yml` workflow:

1. Measures baseline coverage on `master`
2. Runs the suite on your branch
3. Fails the job if coverage < 85% (no PR is opened)
4. Detects any changes to `upload/` and flags them in the PR description
5. Opens (or updates) the PR with a before/after coverage table

---

## PR description format

The generated PR description always includes:

```
## Coverage report: `<module>`

|        | Line coverage |
|--------|---------------|
| Before | X.X%          |
| After  | Y.Y% ▲        |

## Checklist
- [x] Suite is green on PHP 8.2, 8.3, 8.4
- [x] Line coverage ≥ 85% (Y.Y%)
- [x] No application code modified
```

If application code was modified, an additional `⚠️ Application code modified`
section lists every changed file and requires a written justification before
the PR can be merged.
