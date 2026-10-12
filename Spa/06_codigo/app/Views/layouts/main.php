<?php
/** CP-FRONT-02: shell autenticado = header + sidebar + contenido + footer */
require __DIR__ . '/header.php';
?>
<body class="page-main">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<?php require __DIR__ . '/sidebar.php'; ?>
<main id="contenido" class="main-content" tabindex="-1">
<?= $content ?>
</main>
<script type="module" src="/assets/js/app.js"></script>
<?php require __DIR__ . '/footer.php';
