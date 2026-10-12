<?php /** CP-FRONT-01: pantalla de login */ ?>
<main class="auth-card">
  <h1>Iniciar sesión</h1>
  <p class="auth-sub">Acceso al sistema</p>
  <form id="login-form" novalidate>
    <div class="form-field">
      <label for="email">Correo electrónico</label>
      <input type="email" id="email" name="email" autocomplete="username" required>
    </div>
    <div class="form-field">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <p id="login-error" class="form-error" role="alert" hidden></p>
    <button type="submit" class="btn btn-primary" id="login-submit">Entrar</button>
  </form>
</main>
<script type="module" src="/assets/js/modules/login.js"></script>
