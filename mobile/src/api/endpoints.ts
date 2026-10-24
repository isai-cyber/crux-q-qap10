import { apiFetch } from './client';
import type {
  Activo,
  ChatResponse,
  DashboardResponse,
  Diagnostico,
  LoginResponse,
  MeResponse,
  Pagina,
  Ticket,
} from './types';

export function login(usuario: string, password: string, nombreDispositivo: string) {
  return apiFetch<LoginResponse>('/api/v1/auth/login', {
    method: 'POST',
    body: { usuario, password, nombre_dispositivo: nombreDispositivo },
  });
}

export function me() {
  return apiFetch<MeResponse>('/api/v1/me');
}

export function tickets(estado?: string, page = 1) {
  const params = new URLSearchParams({ page: String(page) });
  if (estado) params.set('estado', estado);
  return apiFetch<Pagina<Ticket>>(`/api/v1/tickets?${params.toString()}`);
}

export function ticket(id: number) {
  return apiFetch<Ticket>(`/api/v1/tickets/${id}`);
}

export function cambiarEstado(id: number, estado: string) {
  return apiFetch<Ticket>(`/api/v1/tickets/${id}/estado`, {
    method: 'POST',
    body: { estado },
  });
}

export function activos(q?: string, page = 1) {
  const params = new URLSearchParams({ page: String(page) });
  if (q) params.set('q', q);
  return apiFetch<Pagina<Activo>>(`/api/v1/activos?${params.toString()}`);
}

export function activo(id: number) {
  return apiFetch<Activo & { diagnosticos: Diagnostico[] }>(`/api/v1/activos/${id}`);
}

export function chat(mensaje: string, activoId?: number) {
  return apiFetch<ChatResponse>('/api/v1/chat', {
    method: 'POST',
    body: { mensaje, ...(activoId ? { activo_id: activoId } : {}) },
  });
}

export function dashboard() {
  return apiFetch<DashboardResponse>('/api/v1/dashboard');
}
