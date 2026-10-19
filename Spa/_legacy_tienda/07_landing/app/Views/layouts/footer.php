<?php
// View: footer cierre de página (CP-LAND-06). Sin SQL/PDO.
$wa = $landing['whatsapp'];
$email = $landing['contact_email'];
?>
</main>
<footer class="site-footer" id="contacto">
  <div class="container">
    <p><strong><?= htmlspecialchars($landing['brand_name'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p class="demo-notice"><?= htmlspecialchars($landing['demo_notice'], ENT_QUOTES, 'UTF-8') ?></p>
    <p class="demo-notice">
      <?php if ($email !== null && $email !== ''): ?>
        Contacto: <a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></a>
      <?php else: ?>
        <!-- Sin email aprobado: no se inventan datos de contacto. -->
        Contacto disponible próximamente.
      <?php endif; ?>
    </p>
  </div>
</footer>
<script src="assets/js/landing.js" defer></script>
</body>
</html>
