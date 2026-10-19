<?php
// View: Sección Productos (CP-LAND-08). Recibe $productosGrupo desde el front controller.
$grupos = $productosGrupo ?? [];
$e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bs = static fn($n): string => 'Bs ' . number_format((float) $n, 2, ',', '.');
$total = array_sum(array_map(fn($g) => count($g['productos']), $grupos));
?>
<section class="section productos" id="productos" aria-labelledby="productos-titulo">
  <div class="container">
    <span class="badge">Productos</span>
    <h2 id="productos-titulo">Productos destacados</h2>
    <p class="section__lead"><?= $total ?> productos con precios en Bs.</p>

    <?php if (!$grupos): ?>
      <p class="state state--empty">No hay productos disponibles por el momento.</p>
    <?php else: ?>
      <?php foreach ($grupos as $g): ?>
        <div class="productos__grupo">
          <h3 class="productos__subtitulo"><?= $e($g['categoria']['nombre']) ?></h3>
          <ul class="productos__grid">
            <?php foreach ($g['productos'] as $p): ?>
              <li class="prod-card">
                <img class="prod-card__img"
                     src="<?= $e($p['imagen_url']) ?>"
                     alt="<?= $e($p['nombre']) ?>"
                     width="640" height="480" loading="lazy" decoding="async">
                <div class="prod-card__body">
                  <?php if (!empty($p['etiqueta'])): ?>
                    <span class="prod-card__etiqueta"><?= $e($p['etiqueta']) ?></span>
                  <?php endif; ?>
                  <span class="prod-card__nombre"><?= $e($p['nombre']) ?></span>
                  <span class="prod-card__precio">
                    <span class="prod-card__precio-actual"><?= $bs($p['precio']) ?></span>
                    <?php if ($p['precio_anterior'] !== null): ?>
                      <s class="prod-card__precio-anterior"><?= $bs($p['precio_anterior']) ?></s>
                    <?php endif; ?>
                  </span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
