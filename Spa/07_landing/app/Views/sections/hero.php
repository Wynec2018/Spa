<?php
// View: Hero asimétrico + CTA above-the-fold (CP-LAND-06). Sin SQL/PDO.
// Imagen hero: Commons verificada en CP-LAND-04 (HTTP 200, CC BY 2.0, Bex Walton).
$heroSrc = 'https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9a/Quinoa_grain_bowl_brunch_at_Caravan_Bankside%2C_London%2C_UK_%2836736487911%29.jpg/960px-Quinoa_grain_bowl_brunch_at_Caravan_Bankside%2C_London%2C_UK_%2836736487911%29.jpg';
?>
<section class="hero" aria-labelledby="hero-titulo">
  <div class="container hero__grid">
    <div class="hero__content">
      <span class="badge hero__eyebrow"><?= htmlspecialchars($landing['hero_eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
      <h1 id="hero-titulo" class="hero__title"><?= htmlspecialchars($landing['hero_headline'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="hero__subtitle"><?= htmlspecialchars($landing['hero_subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
      <div class="hero__actions">
        <a class="btn btn--primary" href="<?= htmlspecialchars($landing['primary_cta_target'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($landing['primary_cta_label'], ENT_QUOTES, 'UTF-8') ?></a>
        <a class="btn btn--secondary" href="<?= htmlspecialchars($landing['secondary_cta_target'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($landing['secondary_cta_label'], ENT_QUOTES, 'UTF-8') ?></a>
      </div>
      <p class="demo-notice"><?= htmlspecialchars($landing['demo_notice'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <figure class="hero__figure">
      <img class="hero__img" src="<?= htmlspecialchars($heroSrc, ENT_QUOTES, 'UTF-8') ?>"
           alt="Bowl de quinua con verduras frescas sobre mesa de madera"
           width="960" height="720" fetchpriority="high" decoding="async">
      <figcaption class="hero__caption">Imagen: Bex Walton, CC BY 2.0, Wikimedia Commons.</figcaption>
    </figure>
  </div>
</section>
