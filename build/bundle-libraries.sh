#!/usr/bin/env bash
set -euo pipefail

original=$(mktemp)
cp composer.json "$original"
trap 'cp "$original" composer.json; rm -f "$original"' EXIT

composer extract-dependencies --dump-to-file --fail
composer bundle

# TER reads ext_emconf.php statically, so the autoload must be a literal array
php <<'PHP'
<?php
function export(array $value, int $level): string
{
    $indent = str_repeat('    ', $level + 1);
    $lines = [];
    foreach ($value as $key => $item) {
        $lines[] = $indent
            . (array_is_list($value) ? '' : var_export($key, true) . ' => ')
            . (is_array($item) ? export($item, $level + 1) : var_export($item, true)) . ',';
    }
    return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $level) . ']';
}

$composer = json_decode(file_get_contents('composer.json'), true, 512, JSON_THROW_ON_ERROR);
$emConf = preg_replace(
    '/^    \'autoload\' => (?:\[\]|\[\n.*?\n    \]),$/ms',
    '    \'autoload\' => ' . str_replace(['\\', '$'], ['\\\\', '\\$'], export($composer['autoload'], 1)) . ',',
    file_get_contents('ext_emconf.php'),
    -1,
    $count
);
if ($count !== 1) {
    fwrite(STDERR, "ext_emconf.php: expected exactly one 'autoload' array, found $count\n");
    exit(1);
}
file_put_contents('ext_emconf.php', $emConf);
PHP
