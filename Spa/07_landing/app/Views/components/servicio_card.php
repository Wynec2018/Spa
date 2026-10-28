<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<li class="service-row" data-reveal>
    <span class="service-row__index" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
    <div class="service-row__body">
        <h3 class="service-row__name"><?= $view->escape($servicio['nombre']) ?></h3>
        <p class="service-row__meta">Duración: <?= $view->escape($servicio['duracion']) ?></p>
    </div>
    <p class="service-row__price"><?= $view->escape($servicio['precio']) ?></p>
</li>
