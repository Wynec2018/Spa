// CP-FRONT-02: bootstrap global — botón de sesión del layout autenticado
import { logout } from './api/auth-api.js';

const logoutBtn = document.getElementById('logout-btn');
logoutBtn?.addEventListener('click', async () => {
  try {
    await logout();
  } finally {
    window.location.assign('/login');
  }
});
