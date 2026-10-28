<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sources = [
    'public/assets/css/variables.css',
    'public/assets/css/reset.css',
    'public/assets/css/base.css',
    'public/assets/css/layout.css',
    'public/assets/css/components.css',
    'public/assets/css/landing.css',
    'public/assets/css/responsive.css',
];

$contents = [];
foreach ($sources as $relative) {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        fwrite(STDERR, 'Falta ' . $relative . PHP_EOL);
        exit(1);
    }

    $contents[] = (string) file_get_contents($path);
}

$hash = hash('sha256', implode("\n", $contents));
$banner = '/*! Spa Cielo landing bundle | css-bundle-sha256: ' . $hash
    . ' | generado por tools/build_css_bundle.php */' . "\n";
$output = $banner . implode("\n", $contents) . "\n";
$target = $root . '/public/assets/css/bundle.css';

if (file_put_contents($target, $output, LOCK_EX) === false) {
    fwrite(STDERR, 'No se pudo escribir ' . $target . PHP_EOL);
    exit(1);
}

echo 'OK ' . $target . ' (' . strlen($output) . ' bytes, sha256 ' . $hash . ')' . PHP_EOL;
