// CP-FRONT-03..05: API de categorías
async function send(method, path, payload = null) {
  const res = await fetch(path, {
    method,
    headers: payload ? { 'Content-Type': 'application/json' } : undefined,
    body: payload ? JSON.stringify(payload) : undefined,
    credentials: 'same-origin',
  });
  let data = null;
  try { data = await res.json(); } catch { /* sin JSON */ }
  if (!res.ok || !data?.success) {
    const err = new Error(data?.error ?? 'Error de conexión');
    err.status = res.status;
    throw err;
  }
  return data.data;
}

export const listCategorias = () => send('GET', '/api/v1/categorias');
export const getCategoria = (id) => send('GET', `/api/v1/categorias/${id}`);
export const createCategoria = (payload) => send('POST', '/api/v1/categorias', payload);

// CP-FRONT-05: edición + inactivación (DELETE = lógico, idempotente)
export const updateCategoria = (id, payload) => send('PUT', `/api/v1/categorias/${id}`, payload);
export const deleteCategoria = (id) => send('DELETE', `/api/v1/categorias/${id}`);
