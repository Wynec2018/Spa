// CP-FRONT-01: acceso a la API de autenticación (Fetch API, same-origin)

async function post(path, body) {
  const res = await fetch(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });
  let data = null;
  try { data = await res.json(); } catch { /* respuesta sin JSON */ }
  if (!res.ok || !data?.success) {
    const err = new Error(data?.error ?? 'Error de conexión');
    err.status = res.status;
    throw err;
  }
  return data.data;
}

export function login(email, password) {
  return post('/api/v1/auth/login', { email, password });
}

export function logout() {
  return post('/api/v1/auth/logout', {});
}

export async function me() {
  const res = await fetch('/api/v1/auth/me', { credentials: 'same-origin' });
  let data = null;
  try { data = await res.json(); } catch { /* respuesta sin JSON */ }
  if (!res.ok || !data?.success) {
    const err = new Error(data?.error ?? 'No autenticado');
    err.status = res.status;
    throw err;
  }
  return data.data;
}
