<?php
// View: head + header sticky (CP-LAND-06). Sin SQL/PDO.
$brand = $landing['brand_name'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($landing['meta_title'], ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($landing['meta_description'], ENT_QUOTES, 'UTF-8') ?>">
<meta name="theme-color" content="#f9fafb">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="icon" href="data:,">
<link rel="stylesheet" href="assets/css/variables.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/landing.css">
<link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="site-header__brand" href="#contenido"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></a>
    <nav class="site-header__nav" aria-label="Principal">
      <a href="#categorias">Categorías</a>
      <a href="#productos">Productos</a>
      <a href="#beneficios">Beneficios</a>
      <a href="#contacto">Contacto</a>
    </nav>
    <a class="btn btn--primary site-header__cta" href="<?= htmlspecialchars($landing['primary_cta_target'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($landing['primary_cta_label'], ENT_QUOTES, 'UTF-8') ?></a>
  </div>
</header>
<main id="contenido">
