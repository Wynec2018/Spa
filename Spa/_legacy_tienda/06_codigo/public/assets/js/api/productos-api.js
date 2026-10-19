// CP-FRONT-06..07: API de productos
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

export const listProductos = () => send('GET', '/api/v1/productos');
export const getProducto = (id) => send('GET', `/api/v1/productos/${id}`);

// CP-FRONT-07: alta | CP-FRONT-08: edición + inactivación (DELETE = lógico, idempotente)
export const createProducto = (payload) => send('POST', '/api/v1/productos', payload);
export const updateProducto = (id, payload) => send('PUT', `/api/v1/productos/${id}`, payload);
export const deleteProducto = (id) => send('DELETE', `/api/v1/productos/${id}`);
