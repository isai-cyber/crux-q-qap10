/**
 * Tokens de diseño — misma identidad navy que la web (public/css/app.css).
 */
export const colors = {
  navy: '#0F2D59',
  navyDark: '#0B2144',
  blue: '#0284C7',
  blueDark: '#0369A1',
  cyan: '#0EA5E9',

  bg: '#F8FAFC',
  surface: '#FFFFFF',
  surfaceMuted: '#F1F5F9',
  border: '#E2E8F0',
  borderHover: '#CBD5E1',

  text: '#0F172A',
  textMuted: '#475569',
  textSoft: '#64748B',
  textLight: '#94A3B8',

  emerald: '#059669',
  amber: '#D97706',
  rose: '#E11D48',
  white: '#FFFFFF',
};

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
};

export const radius = {
  sm: 8,
  md: 10,
  lg: 14,
  xl: 20,
  full: 999,
};

export const font = {
  regular: 'PlusJakartaSans_400Regular',
  medium: 'PlusJakartaSans_500Medium',
  semiBold: 'PlusJakartaSans_600SemiBold',
  bold: 'PlusJakartaSans_700Bold',
  extraBold: 'PlusJakartaSans_800ExtraBold',
  mono: 'JetBrainsMono_400Regular',
};

/** Estados válidos de un ticket (mismo contrato que la API). */
export const ESTADOS_TICKET = [
  'abierto',
  'en_progreso',
  'esperando_repuesto',
  'cerrado',
  'cancelado',
] as const;

export type EstadoTicket = (typeof ESTADOS_TICKET)[number];

export const estadoLabel: Record<EstadoTicket, string> = {
  abierto: 'Abierto',
  en_progreso: 'En progreso',
  esperando_repuesto: 'Esperando repuesto',
  cerrado: 'Cerrado',
  cancelado: 'Cancelado',
};

export const estadoColor: Record<EstadoTicket, string> = {
  abierto: colors.blue,
  en_progreso: colors.emerald,
  esperando_repuesto: colors.amber,
  cerrado: colors.textSoft,
  cancelado: colors.rose,
};

export type Prioridad = 'critica' | 'alta' | 'media' | 'baja';

export const prioridadColor: Record<Prioridad, string> = {
  critica: colors.rose,
  alta: colors.amber,
  media: '#CA8A04',
  baja: colors.textSoft,
};

export const prioridadLabel: Record<Prioridad, string> = {
  critica: 'Crítica',
  alta: 'Alta',
  media: 'Media',
  baja: 'Baja',
};
