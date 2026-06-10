<?php
/**
 * Coverage threshold enforcement gate.
 *
 * PHPUnit 11 does not support <minimum> in phpunit.xml.
 * This script parses the Clover XML report and exits non-zero
 * if line coverage drops below the configured threshold.
 *
 * Usage: php scripts/check-coverage-threshold.php [threshold]
 *   threshold defaults to 85 (percent).
 */
$threshold = (int)($argv[1] ?? 85);
$cloverFile = __DIR__ . '/../coverage/clover.xml';

if (!is_file($cloverFile)) {
    fwrite(STDERR, "Error: Clover report not found at {$cloverFile}\n");
    fwrite(STDERR, "Run: phpunit --coverage-clover coverage/clover.xml\n");
    exit(1);
}

$xml = simplexml_load_file($cloverFile);

if (!$xml) {
    fwrite(STDERR, "Error: Could not parse {$cloverFile}\n");
    exit(1);
}

$metrics = $xml->xpath('//project/metrics');

$totalStatements = 0;
$coveredStatements = 0;

foreach ($metrics as $m) {
    $totalStatements += (int)$m['statements'];
    $coveredStatements += (int)$m['coveredstatements'];
}

if ($totalStatements === 0) {
    fwrite(STDERR, "Error: No statements found in coverage report.\n");
    exit(1);
}

$percentage = round(($coveredStatements / $totalStatements) * 100, 2);

echo "Line coverage: {$percentage}% ({$coveredStatements}/{$totalStatements})\n";
echo "Threshold:     {$threshold}%\n";

if ($percentage < $threshold) {
    fwrite(STDERR, "FAIL: Coverage {$percentage}% is below the {$threshold}% threshold.\n");
    exit(1);
}

echo "OK: Coverage meets the minimum threshold.\n";
exit(0);
