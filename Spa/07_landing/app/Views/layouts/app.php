<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $view->escape($meta['title'] ?? 'Spa Cielo') ?></title>
    <meta name="description" content="<?= $view->escape($meta['description'] ?? '') ?>">
    <meta name="theme-color" content="#f7f2ea">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <?php if (($hero['srcset'] ?? '') !== '' && ($hero['sizes'] ?? '') !== ''): ?>
        <link rel="preload" as="image" href="<?= $view->escape($hero['image']) ?>" imagesrcset="<?= $view->escape($hero['srcset']) ?>" imagesizes="<?= $view->escape($hero['sizes']) ?>" fetchpriority="high">
    <?php elseif (($hero['image'] ?? '') !== ''): ?>
        <link rel="preload" as="image" href="<?= $view->escape($hero['image']) ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $view->escape($view->asset('assets/css/bundle.css')) ?>">
    <link rel="stylesheet" href="<?= $view->escape($view->asset('assets/css/motion.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <?= $view->render('layouts.header', ['view' => $view, 'brand' => $brand, 'cta' => $cta]) ?>
    <main id="contenido" tabindex="-1">
        <?= $content ?>
    </main>
    <?= $view->render('layouts.footer', ['view' => $view, 'brand' => $brand, 'year' => $year]) ?>
    <script src="<?= $view->escape($view->asset('assets/js/landing.js')) ?>" defer></script>
</body>
</html>
