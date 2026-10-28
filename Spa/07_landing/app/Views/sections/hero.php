<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<section class="hero" id="inicio" aria-labelledby="hero-title">
    <div class="container hero__grid">
        <div class="hero__content">
            <h1 class="hero__title" id="hero-title">Un espacio para volver a ti.</h1>
            <p class="lede hero__lede">
                Servicios de spa y descanso en un entorno cálido. Consulta nuestros
                servicios y la disponibilidad referencial de camas.
            </p>
            <div class="hero__actions">
                <a class="button" href="<?= $view->escape($cta['url']) ?>">
                    <?= $view->escape($cta['label']) ?>
                </a>
            </div>
            <p class="hero__note">Atención en Bolivia. Disponibilidad referencial, sin reserva web.</p>
        </div>
        <div class="hero__media">
            <div class="image-frame image-frame--wide hero__image-frame">
                <img
                    src="<?= $view->escape($hero['image']) ?>"
                    <?php if (($hero['srcset'] ?? '') !== ''): ?>srcset="<?= $view->escape($hero['srcset']) ?>"
                    sizes="<?= $view->escape($hero['sizes']) ?>"
                    <?php endif; ?>alt="<?= $view->escape($hero['alt']) ?>"
                    width="1400"
                    height="934"
                    fetchpriority="high"
                    data-fallback="<?= $view->escape($hero['fallback']) ?>"
                >
            </div>
            <p class="hero__caption">Sala de masajes y zonas de descanso.</p>
        </div>
    </div>
</section>
