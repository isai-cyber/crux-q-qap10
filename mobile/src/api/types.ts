export type Usuario = {
  id: number;
  nombre: string;
  usuario: string;
  email: string;
  rol: 'admin' | 'supervisor' | 'tecnico';
};

export type LoginResponse = {
  token: string;
  usuario: Usuario;
  organizacion_id: number;
  permisos: string[];
};

export type MeResponse = {
  usuario: Usuario & { organizacion_id: number };
  permisos: string[];
};

export type Ticket = {
  id: number;
  folio: string;
  activo_id: number | null;
  activo_nombre?: string | null;
  descripcion: string;
  categoria_ia: string | null;
  prioridad_ia: 'critica' | 'alta' | 'media' | 'baja' | null;
  estado: string;
  fecha_apertura: string;
  fecha_cierre: string | null;
};

export type Pagina<T> = {
  items: T[];
  total: number;
  page: number;
  per_page: number;
};

export type Activo = {
  id: number;
  nombre: string;
  tipo: string | null;
  ubicacion: string | null;
  ip_address: string | null;
  estado: string;
  planta_nombre?: string | null;
};

export type Diagnostico = {
  id: number;
  descripcion: string;
  created_at: string;
};

export type ChatResponse = {
  respuesta: string;
  ticket: { id: number; folio: string } | null;
};

export type DashboardResponse = {
  kpis: {
    tickets_abiertos: number;
    tickets_criticos: number;
    activos_fuera_servicio: number;
    productos_sin_stock: number;
  };
  tickets_recientes: Ticket[];
  tickets_por_estado: { estado: string; total: number }[];
};
