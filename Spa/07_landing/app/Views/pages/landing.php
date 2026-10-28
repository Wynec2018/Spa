<?php

declare(strict_types=1);

/** @var App\Core\View $view */
?>
<div class="landing">
    <?= $view->render('sections.hero', ['view' => $view, 'hero' => $hero, 'cta' => $cta]) ?>
    <?= $view->render('sections.servicios', [
        'view' => $view,
        'servicios' => $servicios,
        'services_error' => $services_error,
        'currency' => $currency,
    ]) ?>
    <?= $view->render('sections.camas', [
        'view' => $view,
        'camas' => $camas,
        'beds_error' => $beds_error,
        'total_camas' => $total_camas,
        'camas_libres' => $camas_libres,
    ]) ?>
    <?= $view->render('sections.beneficios', ['view' => $view]) ?>
    <?= $view->render('sections.prueba_social', ['view' => $view]) ?>
    <?= $view->render('sections.llamada_accion', [
        'view' => $view,
        'cta' => $cta,
        'contact' => $contact,
    ]) ?>
</div>
