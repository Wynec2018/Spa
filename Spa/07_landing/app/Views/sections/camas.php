<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<section class="section beds landing-section" id="camas" aria-labelledby="camas-title">
    <div class="container beds__grid">
        <div class="beds__intro" data-reveal>
            <h2 id="camas-title">Camas para tu descanso.</h2>
            <p class="lede">Un resumen agregado de las camas registradas. No mostramos identificadores ni datos de personas.</p>
            <p class="beds__note">La disponibilidad es referencial y puede cambiar. No es una reserva en tiempo real.</p>
        </div>

        <div class="beds__panel" data-reveal>
            <?php if ($beds_error): ?>
                <p class="beds__notice" role="status">No pudimos cargar la disponibilidad en este momento. Intenta nuevamente.</p>
            <?php elseif ($camas === []): ?>
                <p class="beds__notice" role="status">No hay camas registradas para mostrar.</p>
            <?php else: ?>
                <p class="beds__total">
                    <span class="beds__total-value"><?= $view->escape((string) $camas_libres) ?></span>
                    <span class="beds__total-label">
                        <?= $camas_libres === 1 ? 'cama libre' : 'camas libres' ?><br>
                        <span class="beds__total-sub">de <?= $view->escape((string) $total_camas) ?> <?= $total_camas === 1 ? 'registrada' : 'registradas' ?></span>
                    </span>
                </p>
                <ul class="beds__list">
                    <?php foreach ($camas as $cama): ?>
                        <?= $view->render('components.cama_resumen', [
                            'view' => $view,
                            'cama' => $cama,
                        ]) ?>
                    <?php endforeach; ?>
                </ul>
                <p class="beds__footnote">Resumen por estado, sin camas individuales.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
