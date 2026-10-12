<?php
// View: CTA final (CP-LAND-09). CTA visible; cero login.
$e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<section class="cta-final" id="cta-final" aria-labelledby="cta-final-titulo">
  <div class="container cta-final__inner">
    <div>
      <h2 id="cta-final-titulo">Explora las ofertas de hoy</h2>
      <p class="cta-final__lead"><?= $e($landing['demo_notice']) ?></p>
    </div>
    <div class="cta-final__acciones">
      <a class="btn btn--primary" href="<?= $e($landing['primary_cta_target']) ?>"><?= $e($landing['primary_cta_label']) ?></a>
      <a class="btn btn--secondary btn--on-accent" href="<?= $e($landing['secondary_cta_target']) ?>"><?= $e($landing['secondary_cta_label']) ?></a>
    </div>
  </div>
</section>
