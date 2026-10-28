<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<li class="cama-row cama-row--<?= $view->escape($cama['estado']) ?>">
    <span class="cama-row__label"><?= $view->escape($cama['label']) ?></span>
    <span class="cama-row__count"><?= $view->escape((string) $cama['total']) ?></span>
</li>
