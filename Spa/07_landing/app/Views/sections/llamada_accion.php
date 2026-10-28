<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<section class="section cta-final landing-section" id="cierre" aria-labelledby="cierre-title" data-cta="final">
    <div class="container cta-final__inner">
        <div class="cta-final__content" data-reveal>
            <h2 id="cierre-title">Elige tu pausa.</h2>
            <p class="lede">Revisa los servicios y la disponibilidad referencial. Si necesitas hablar con nosotros, usa el canal disponible.</p>
        </div>

        <div class="cta-final__actions" data-reveal>
            <?php if ($contact['whatsapp'] !== null): ?>
                <a class="button" href="<?= $view->escape($contact['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Escribir por WhatsApp<span class="visually-hidden"> (abre en una pestaña nueva)</span></a>
            <?php endif; ?>

            <?php if ($contact['email'] !== null): ?>
                <a class="button<?= $contact['whatsapp'] !== null ? ' button--ghost' : '' ?>" href="mailto:<?= $view->escape($contact['email']) ?>">Escribir por correo</a>
            <?php endif; ?>

            <?php if ($contact['whatsapp'] === null && $contact['email'] === null): ?>
                <a class="button" href="<?= $view->escape($cta['url']) ?>"><?= $view->escape($cta['label']) ?></a>
            <?php endif; ?>

            <a class="button button--ghost" href="#camas">Ver disponibilidad</a>
        </div>

        <p class="cta-final__note">Sin formularios ni cobros en línea. La disponibilidad es referencial.</p>
    </div>
</section>
