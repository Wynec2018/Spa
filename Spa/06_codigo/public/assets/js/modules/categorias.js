// CP-FRONT-03: listado con estados | CP-FRONT-04: alta | CP-FRONT-05: editar/inactivar
import {
  listCategorias, createCategoria, updateCategoria, deleteCategoria,
} from '../api/categorias-api.js';

const tbody = document.getElementById('cat-tbody');
const table = document.getElementById('cat-table');
const loading = document.getElementById('cat-loading');
const empty = document.getElementById('cat-empty');
const errorEl = document.getElementById('cat-error');
const form = document.getElementById('cat-new-form');
const formTitle = document.getElementById('cat-new-title');
const submitBtn = document.getElementById('cat-new-submit');
const cancelBtn = document.getElementById('cat-cancel-edit');
const formErr = document.getElementById('cat-new-error');
const formOk = document.getElementById('cat-new-ok');

let allRows = [];
let editId = null;

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

function fillParentSelect(excludeId = null) {
  const select = document.getElementById('cat-padre');
  if (!select) { return; }
  select.innerHTML = '<option value="">— Ninguna —</option>'
    + allRows
      .filter((r) => r.estado === 'activo' && String(r.id) !== String(excludeId))
      .map((r) => `<option value="${esc(r.id)}">${esc(r.nombre)}</option>`)
      .join('');
}

function rowActions(r) {
  const editar = `<button type="button" class="btn btn-ghost btn-sm" data-action="edit" data-id="${esc(r.id)}">Editar</button>`;
  if (r.estado !== 'activo') {
    return editar;
  }
  return `${editar}<button type="button" class="btn btn-ghost btn-sm" data-action="deactivate" data-id="${esc(r.id)}">Inactivar</button>`;
}

async function init() {
  try {
    allRows = await listCategorias();
    loading.hidden = true;
    if (!Array.isArray(allRows) || allRows.length === 0) {
      empty.hidden = false;
      return;
    }
    fillParentSelect();
    tbody.innerHTML = allRows.map((r) => `
      <tr data-id="${esc(r.id)}">
        <td>${esc(r.id)}</td>
        <td>${esc(r.nombre)}</td>
        <td>${r.categoria_padre_id ? esc(r.categoria_padre_id) : '—'}</td>
        <td><span class="badge badge-${r.estado === 'activo' ? 'ok' : 'off'}">${esc(r.estado)}</span></td>
        <td class="row-actions">${rowActions(r)}</td>
      </tr>`).join('');
    table.hidden = false;
  } catch (err) {
    loading.hidden = true;
    errorEl.textContent = err.message || 'Error cargando categorías';
    errorEl.hidden = false;
  }
}

init();

// ---- alta / edición (form compartido) ----
function resetForm() {
  editId = null;
  form.reset();
  formTitle.textContent = 'Nueva categoría';
  submitBtn.textContent = 'Crear categoría';
  cancelBtn.hidden = true;
  formErr.hidden = true;
  formOk.hidden = true;
}

cancelBtn?.addEventListener('click', resetForm);

tbody?.addEventListener('click', (event) => {
  const btn = event.target.closest('button[data-action]');
  if (!btn) { return; }
  const id = Number(btn.dataset.id);
  const row = allRows.find((r) => Number(r.id) === id);
  if (!row) { return; }
  if (btn.dataset.action === 'edit') {
    editId = id;
    formTitle.textContent = 'Editar categoría';
    submitBtn.textContent = 'Guardar cambios';
    cancelBtn.hidden = false;
    formErr.hidden = true;
    formOk.hidden = true;
    fillParentSelect(id);
    form.nombre.value = row.nombre;
    form.categoria_padre_id.value = row.categoria_padre_id ?? '';
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.nombre.focus();
    return;
  }
  if (btn.dataset.action === 'deactivate') {
    // confirm() nativo: suficiente para una inactivación con confirmación explícita
    if (!window.confirm(`¿Inactivar la categoría "${row.nombre}"?`)) { return; }
    btn.disabled = true;
    deleteCategoria(id)
      .then(() => window.location.reload())
      .catch((err) => {
        errorEl.textContent = err.message || 'Error inactivando la categoría';
        errorEl.hidden = false;
        btn.disabled = false;
      });
  }
});

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  formErr.hidden = true;
  formOk.hidden = true;
  const nombre = form.nombre.value.trim();
  if (!nombre) {
    formErr.textContent = 'El nombre es obligatorio.';
    formErr.hidden = false;
    return;
  }
  const padreId = form.categoria_padre_id.value;
  const payload = {
    nombre,
    ...(padreId ? { categoria_padre_id: Number(padreId) } : {}),
  };
  submitBtn.disabled = true;
  try {
    if (editId) {
      await updateCategoria(editId, payload);
      formOk.textContent = 'Categoría actualizada.';
    } else {
      await createCategoria(payload);
      formOk.textContent = 'Categoría creada.';
    }
    formOk.hidden = false;
    setTimeout(() => window.location.reload(), 600);
  } catch (err) {
    formErr.textContent = err.message
      || (editId ? 'Error actualizando la categoría' : 'Error creando la categoría');
    formErr.hidden = false;
    submitBtn.disabled = false;
  }
});
