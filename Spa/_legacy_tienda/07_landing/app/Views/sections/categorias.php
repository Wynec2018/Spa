<?php
// View: Sección Categorías (CP-LAND-07). Recibe $categorias desde el front controller.
$items = $categorias ?? [];
$e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<section class="section categorias" id="categorias" aria-labelledby="categorias-titulo">
  <div class="container">
    <span class="badge">Categorías</span>
    <h2 id="categorias-titulo">Explora por categoría</h2>
    <p class="section__lead"><?= count($items) ?> categorías con productos en Bs.</p>

    <?php if (!$items): ?>
      <p class="state state--empty">No hay categorías disponibles por el momento.</p>
    <?php else: ?>
      <ul class="categorias__grid">
        <?php foreach ($items as $cat): ?>
          <li class="cat-card">
            <a class="cat-card__link" href="#productos">
              <img class="cat-card__img"
                   src="<?= $e($cat['imagen_url']) ?>"
                   alt="Categoría <?= $e($cat['nombre']) ?>"
                   width="640" height="480" loading="lazy" decoding="async">
              <span class="cat-card__body">
                <span class="cat-card__nombre"><?= $e($cat['nombre']) ?></span>
                <?php if (!empty($cat['descripcion'])): ?>
                  <span class="cat-card__desc"><?= $e($cat['descripcion']) ?></span>
                <?php endif; ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>
