<?php
// View: Prueba social (CP-LAND-09). Testimonios ficticios => identificados como demostrativos.
$e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$testimonios = [
    ['nombre' => 'María G.', 'ciudad' => 'La Paz', 'texto' => 'Encontré precios claros en Bs para comparar productos sin registrarme.'],
    ['nombre' => 'Carlos R.', 'ciudad' => 'Cochabamba', 'texto' => 'La navegación por categorías es rápida y el catálogo se ve completo.'],
];
?>
<section class="section prueba-social" id="prueba-social" aria-labelledby="prueba-social-titulo">
  <div class="container">
    <span class="badge">Prueba social</span>
    <h2 id="prueba-social-titulo">Lo que dicen los usuarios</h2>
    <p class="section__lead">Testimonios de demostración: son ficticios y existen solo para ilustrar la sección.</p>
    <ul class="testimonios">
      <?php foreach ($testimonios as $t): ?>
        <li class="testimonio">
          <blockquote class="testimonio__texto">“<?= $e($t['texto']) ?>”</blockquote>
          <p class="testimonio__autor">
            <strong><?= $e($t['nombre']) ?></strong>
            <span><?= $e($t['ciudad']) ?></span>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
