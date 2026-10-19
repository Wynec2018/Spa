// CP-FRONT-06: listado | CP-FRONT-07: alta | CP-FRONT-08: editar/inactivar
import {
  listProductos, createProducto, updateProducto, deleteProducto,
} from '../api/productos-api.js';
import { listCategorias } from '../api/categorias-api.js';

const tbody = document.getElementById('prod-tbody');
const table = document.getElementById('prod-table');
const loading = document.getElementById('prod-loading');
const empty = document.getElementById('prod-empty');
const errorEl = document.getElementById('prod-error');
const form = document.getElementById('prod-new-form');
const formTitle = document.getElementById('prod-new-title');
const submitBtn = document.getElementById('prod-new-submit');
const cancelBtn = document.getElementById('prod-cancel-edit');
const formErr = document.getElementById('prod-new-error');
const formOk = document.getElementById('prod-new-ok');

let allRows = [];
let editId = null;

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

function rowActions(r) {
  const editar = `<button type="button" class="btn btn-ghost btn-sm" data-action="edit" data-id="${esc(r.id)}">Editar</button>`;
  if (r.estado !== 'activo') { return editar; }
  return `${editar}<button type="button" class="btn btn-ghost btn-sm" data-action="deactivate" data-id="${esc(r.id)}">Inactivar</button>`;
}

async function init() {
  try {
    allRows = await listProductos();
    loading.hidden = true;
    if (!Array.isArray(allRows) || allRows.length === 0) {
      empty.hidden = false;
      return;
    }
    tbody.innerHTML = allRows.map((r) => `
      <tr data-id="${esc(r.id)}">
        <td>${esc(r.id)}</td>
        <td>${esc(r.sku)}</td>
        <td>${esc(r.nombre)}</td>
        <td>${r.categoria_id ? esc(r.categoria_id) : '—'}</td>
        <td>${esc(r.unidad)}</td>
        <td><span class="badge badge-${r.estado === 'activo' ? 'ok' : 'off'}">${esc(r.estado)}</span></td>
        <td class="row-actions">${rowActions(r)}</td>
      </tr>`).join('');
    table.hidden = false;
  } catch (err) {
    loading.hidden = true;
    errorEl.textContent = err.message || 'Error cargando productos';
    errorEl.hidden = false;
  }
}

init();

// ---- CP-FRONT-07: alta ----
async function fillCategorySelect() {
  const select = document.getElementById('prod-cat');
  if (!select) { return; }
  try {
    const cats = await listCategorias();
    select.insertAdjacentHTML('beforeend', cats
      .filter((c) => c.estado === 'activo')
      .map((c) => `<option value="${esc(c.id)}">${esc(c.nombre)}</option>`)
      .join(''));
  } catch {
    // el form sigue disponible; el error de la API se mostrará al enviar
  }
}

fillCategorySelect();

function resetForm() {
  editId = null;
  form.reset();
  formTitle.textContent = 'Nuevo producto';
  submitBtn.textContent = 'Crear producto';
  cancelBtn.hidden = true;
  formErr.hidden = true;
  formOk.hidden = true;
}

cancelBtn?.addEventListener('click', resetForm);

// ---- CP-FRONT-08: editar/inactivar ----
tbody?.addEventListener('click', (event) => {
  const btn = event.target.closest('button[data-action]');
  if (!btn) { return; }
  const id = Number(btn.dataset.id);
  const row = allRows.find((r) => Number(r.id) === id);
  if (!row) { return; }
  if (btn.dataset.action === 'edit') {
    editId = id;
    formTitle.textContent = 'Editar producto';
    submitBtn.textContent = 'Guardar cambios';
    cancelBtn.hidden = false;
    formErr.hidden = true;
    formOk.hidden = true;
    form.sku.value = row.sku;
    form.nombre.value = row.nombre;
    form.categoria_id.value = row.categoria_id ?? '';
    form.unidad.value = row.unidad;
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.sku.focus();
    return;
  }
  if (btn.dataset.action === 'deactivate') {
    // confirm() nativo: suficiente para una inactivación con confirmación explícita
    if (!window.confirm(`¿Inactivar el producto "${row.nombre}" (SKU ${row.sku})?`)) { return; }
    btn.disabled = true;
    deleteProducto(id)
      .then(() => window.location.reload())
      .catch((err) => {
        errorEl.textContent = err.message || 'Error inactivando el producto';
        errorEl.hidden = false;
        btn.disabled = false;
      });
  }
});

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  formErr.hidden = true;
  formOk.hidden = true;
  const sku = form.sku.value.trim();
  const nombre = form.nombre.value.trim();
  const unidad = form.unidad.value.trim();
  const catId = form.categoria_id.value;
  if (!sku || !nombre || !unidad || !catId) {
    formErr.textContent = 'SKU, nombre, categoría y unidad son obligatorios.';
    formErr.hidden = false;
    return;
  }
  const payload = { sku, nombre, categoria_id: Number(catId), unidad };
  submitBtn.disabled = true;
  try {
    if (editId) {
      await updateProducto(editId, payload);
      formOk.textContent = 'Producto actualizado.';
    } else {
      await createProducto(payload);
      formOk.textContent = 'Producto creado.';
    }
    formOk.hidden = false;
    setTimeout(() => window.location.reload(), 600);
  } catch (err) {
    formErr.textContent = err.message
      || (editId ? 'Error actualizando el producto' : 'Error creando el producto');
    formErr.hidden = false;
    submitBtn.disabled = false;
  }
});
