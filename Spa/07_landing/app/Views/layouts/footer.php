<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <p class="site-footer__brand" translate="no"><?= $view->escape($brand) ?></p>
        <p class="site-footer__note">Sitio informativo. La disponibilidad de camas es referencial.</p>
        <p class="site-footer__meta">&copy; <?= $view->escape($year) ?> <?= $view->escape($brand) ?></p>
    </div>
</footer>
