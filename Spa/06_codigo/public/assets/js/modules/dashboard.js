// CP-FRONT-02: conteos del dashboard desde la API
import { listCategorias } from '../api/categorias-api.js';
import { listProductos } from '../api/productos-api.js';

const errorEl = document.getElementById('dash-error');

async function countInto(id, fetcher) {
  const el = document.getElementById(id);
  try {
    const rows = await fetcher();
    el.textContent = Array.isArray(rows) ? String(rows.length) : '—';
  } catch (err) {
    el.textContent = '—';
    errorEl.hidden = false;
    errorEl.textContent = err.message || 'Error cargando datos';
  }
}

Promise.all([
  countInto('count-categorias', listCategorias),
  countInto('count-productos', listProductos),
]);
