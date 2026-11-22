#!/usr/bin/env bash
set -euo pipefail

original=$(mktemp)
cp composer.json "$original"
trap 'cp "$original" composer.json; rm -f "$original"' EXIT

composer extract-dependencies --dump-to-file --fail
composer bundle
php -r '
    $composer = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);
    file_put_contents("Resources/Private/Libs/autoload.json", json_encode($composer["autoload"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
'
