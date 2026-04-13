<?php

/**
 * Builds lang/{locale}.json: for each key in lang/_client_ui_keys.json, use the existing translation,
 * then lang/data/extras_translations.php, then fall back to English (the key).
 *
 * Run: php lang/build_client_ui.php
 */
declare(strict_types=1);

$base = __DIR__;
$keysPath = $base.'/_client_ui_keys.json';
if (! is_readable($keysPath)) {
    fwrite(STDERR, "Missing {$keysPath}\n");
    exit(1);
}
$keys = json_decode(file_get_contents($keysPath), true, 512, JSON_THROW_ON_ERROR);
$extrasAll = require $base.'/data/extras_translations.php';

$locales = ['tr', 'es', 'ar', 'ru', 'zh'];

foreach ($locales as $locale) {
    $path = $base.'/'.$locale.'.json';
    $prev = [];
    if (is_readable($path)) {
        $prev = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
    $extra = $extrasAll[$locale] ?? [];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = $prev[$k] ?? $extra[$k] ?? $k;
    }
    file_put_contents(
        $path,
        json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n"
    );
    echo "Wrote {$path}\n";
}
