import * as SecureStore from 'expo-secure-store';
import { API_BASE_URL } from '../config';

const TOKEN_KEY = 'crux_auth_token';

export class ApiError extends Error {
  status: number;
  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

export async function guardarToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function obtenerToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function borrarToken(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}

type Opciones = {
  method?: 'GET' | 'POST';
  body?: unknown;
};

/**
 * Fetch autenticado contra la API de Crux A.I. Adjunta el Bearer guardado en
 * SecureStore y lanza ApiError con el mensaje del servidor en fallos.
 */
export async function apiFetch<T>(path: string, opciones: Opciones = {}): Promise<T> {
  const token = await obtenerToken();

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: opciones.method ?? 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: opciones.body !== undefined ? JSON.stringify(opciones.body) : undefined,
  });

  const texto = await response.text();
  const data = texto ? JSON.parse(texto) : {};

  if (!response.ok) {
    const mensaje =
      (typeof data?.error === 'string' && data.error) ||
      (Array.isArray(data?.errors) && data.errors.join(' ')) ||
      `Error ${response.status}`;
    throw new ApiError(response.status, mensaje);
  }

  return data as T;
}
