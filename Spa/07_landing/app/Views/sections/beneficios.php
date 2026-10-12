<?php
// View: Beneficios (CP-LAND-09). Solo datos verificables del sitio; sin claims falsos.
$e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = [
    ['valor' => (string) ($numeroCategorias ?? 7), 'label' => 'categorías para explorar'],
    ['valor' => (string) ($numeroProductos ?? 70), 'label' => 'productos en el catálogo'],
    ['valor' => 'Bs', 'label' => 'precios en bolivianos'],
    ['valor' => '0', 'label' => 'cuentas o registros requeridos'],
];
?>
<section class="section beneficios" id="beneficios" aria-labelledby="beneficios-titulo">
  <div class="container">
    <span class="badge">Beneficios</span>
    <h2 id="beneficios-titulo">Por qué explorar el catálogo</h2>
    <p class="section__lead">Datos reales de esta demostración, sin promesas exageradas.</p>
    <ul class="beneficios__grid">
      <?php foreach ($items as $it): ?>
        <li class="beneficio">
          <span class="beneficio__valor"><?= $e($it['valor']) ?></span>
          <span class="beneficio__label"><?= $e($it['label']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
