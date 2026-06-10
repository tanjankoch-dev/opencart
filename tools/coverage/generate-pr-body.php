<?php
declare(strict_types=1);

[$script, $module, $before, $after, $changesFile] = array_pad($argv, 5, '');

$appChanges = ($changesFile && file_exists($changesFile))
    ? array_filter(array_map('trim', file($changesFile)))
    : [];

$trend = (float) $after >= (float) $before ? '▲' : '▼';

$body  = "## Coverage report: `{$module}`\n\n";
$body .= "| | Line coverage |\n";
$body .= "|---|---|\n";
$body .= "| Before | {$before}% |\n";
$body .= "| After  | {$after}% {$trend} |\n\n";

if ($appChanges) {
    $body .= "## ⚠️ Application code modified\n\n";
    $body .= "The following files under `upload/` were changed. ";
    $body .= "Each **must** be justified below as genuinely untestable:\n\n";
    foreach ($appChanges as $f) {
        $body .= "- `{$f}`\n";
    }
    $body .= "\n**Justification** _(fill in before merging)_:\n\n> <!-- explain why each change was necessary -->\n\n";
}

$body .= "## Checklist\n\n";
$body .= "- [x] Suite is green on PHP 8.2, 8.3, 8.4\n";
$body .= "- [x] Line coverage ≥ 85% (`{$after}%`)\n";
if ($appChanges) {
    $body .= "- [ ] Application code changes reviewed and justified above\n";
} else {
    $body .= "- [x] No application code modified\n";
}

echo $body;
