// CP-FRONT-01: módulo de la pantalla /login
import { login } from '../api/auth-api.js';

const form = document.getElementById('login-form');
const errorEl = document.getElementById('login-error');
const submitBtn = document.getElementById('login-submit');

function showError(msg) {
  errorEl.textContent = msg;
  errorEl.hidden = false;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorEl.hidden = true;
  submitBtn.disabled = true;
  try {
    await login(form.email.value.trim(), form.password.value);
    window.location.assign('/dashboard');
  } catch (err) {
    showError(err.message || 'Error de conexión');
    submitBtn.disabled = false;
  }
});
