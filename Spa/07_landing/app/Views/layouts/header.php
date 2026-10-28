<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/" aria-label="<?= $view->escape($brand) ?>, inicio">
            <span class="brand__mark" aria-hidden="true">SC</span>
            <span class="brand__name" translate="no"><?= $view->escape($brand) ?></span>
        </a>
        <a class="button button--small" href="<?= $view->escape($cta['url']) ?>">
            <?= $view->escape($cta['label']) ?>
        </a>
    </div>
</header>
