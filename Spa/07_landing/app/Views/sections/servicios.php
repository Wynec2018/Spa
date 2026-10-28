<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<section class="section services landing-section" id="servicios" aria-labelledby="servicios-title">
    <div class="container">
        <div class="section__header services__header" data-reveal>
            <h2 id="servicios-title">Rituales para tu pausa.</h2>
            <p class="lede">Elige el servicio que necesitas. Duraciones y precios referenciales en <?= $view->escape($currency) ?>.</p>
        </div>

        <?php if ($services_error): ?>
            <p class="services__notice" role="status">No pudimos cargar los servicios en este momento. Intenta nuevamente.</p>
        <?php elseif ($servicios === []): ?>
            <p class="services__notice" role="status">No hay servicios activos para mostrar.</p>
        <?php else: ?>
            <ol class="services__list">
                <?php foreach ($servicios as $index => $servicio): ?>
                    <?= $view->render('components.servicio_card', [
                        'view' => $view,
                        'servicio' => $servicio,
                        'index' => $index,
                    ]) ?>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <p class="services__note">Precios referenciales en <?= $view->escape($currency) ?>. La disponibilidad y el precio final se confirman en la sede.</p>
    </div>
</section>
