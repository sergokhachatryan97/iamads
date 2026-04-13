<?php

/**
 * Regenerates lang/_client_ui_keys.json from client-facing Blade templates.
 * Dotted keys like common.social_media are skipped (they live in lang/{locale}/*.php).
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base.'/resources/views/client', FilesystemIterator::SKIP_DOTS)
);
$files = [];
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getPathname(), '.blade.php')) {
        $files[] = $f->getPathname();
    }
}
$files = array_merge($files, [
    $base.'/resources/views/layouts/client-navigation.blade.php',
    $base.'/resources/views/components/client-smm-layout.blade.php',
    $base.'/resources/views/components/client-layout.blade.php',
]);
$keys = [];
foreach ($files as $f) {
    $c = file_get_contents($f);
    if (preg_match_all("/__\(\x27((?:[^\x27\\\\]|\\\\.)*)\x27\)/u", $c, $m)) {
        foreach ($m[1] as $k) {
            $keys[stripcslashes($k)] = true;
        }
    }
}
$flat = [];
foreach (array_keys($keys) as $k) {
    if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i', $k)) {
        continue;
    }
    $flat[] = $k;
}
sort($flat);
$target = __DIR__.'/_client_ui_keys.json';
file_put_contents($target, json_encode($flat, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
echo 'Wrote '.$target.' ('.count($flat)." keys)\n";
