<?php
declare(strict_types=1);

$file   = $argv[1] ?? 'coverage/clover.xml';
$filter = $argv[2] ?? '';

if (!file_exists($file)) {
    echo json_encode(['covered' => 0, 'total' => 0, 'percentage' => 0.0]);
    exit(0);
}

$xml = simplexml_load_file($file);

if ($filter !== '') {
    // Scope metrics to only the matching file(s)
    $metrics = [];
    foreach ($xml->xpath('//file') as $fileNode) {
        $name = (string) $fileNode['name'];
        if (str_contains($name, $filter)) {
            foreach ($fileNode->xpath('metrics') as $m) {
                $metrics[] = $m;
            }
        }
    }
} else {
    $metrics = $xml->xpath('//metrics');
}

$covered = (int) array_sum(array_map(fn($m) => (int) $m['coveredstatements'], $metrics));
$total   = (int) array_sum(array_map(fn($m) => (int) $m['statements'], $metrics));
$pct     = $total > 0 ? round($covered / $total * 100, 1) : 0.0;

echo json_encode(['covered' => $covered, 'total' => $total, 'percentage' => $pct]);
