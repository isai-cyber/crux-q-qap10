import React, {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import * as SecureStore from 'expo-secure-store';
import * as Device from 'expo-device';
import { borrarToken, guardarToken, obtenerToken } from '../api/client';
import { login as loginApi } from '../api/endpoints';
import type { Usuario } from '../api/types';

type AuthState = {
  listo: boolean;
  sesion: boolean;
  usuario: Usuario | null;
  permisos: string[];
  iniciarSesion: (usuario: string, password: string) => Promise<void>;
  cerrarSesion: () => Promise<void>;
  tienePermiso: (permiso: string) => boolean;
};

const AuthContext = createContext<AuthState | null>(null);

const USER_KEY = 'crux_user';
const PERMISOS_KEY = 'crux_permisos';

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [listo, setListo] = useState(false);
  const [sesion, setSesion] = useState(false);
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [permisos, setPermisos] = useState<string[]>([]);

  // Hidratar sesión previa al arrancar (token + perfil en SecureStore).
  useEffect(() => {
    (async () => {
      try {
        const token = await obtenerToken();
        if (token) {
          const usuarioGuardado = await SecureStore.getItemAsync(USER_KEY);
          const permisosGuardados = await SecureStore.getItemAsync(PERMISOS_KEY);
          if (usuarioGuardado && permisosGuardados) {
            setUsuario(JSON.parse(usuarioGuardado));
            setPermisos(JSON.parse(permisosGuardados));
            setSesion(true);
          }
        }
      } finally {
        setListo(true);
      }
    })();
  }, []);

  const iniciarSesion = async (nombreUsuario: string, password: string) => {
    const dispositivo =
      Device.deviceName ?? Device.modelName ?? 'dispositivo';
    const respuesta = await loginApi(nombreUsuario, password, dispositivo);

    await guardarToken(respuesta.token);
    await SecureStore.setItemAsync(USER_KEY, JSON.stringify(respuesta.usuario));
    await SecureStore.setItemAsync(PERMISOS_KEY, JSON.stringify(respuesta.permisos));

    setUsuario(respuesta.usuario);
    setPermisos(respuesta.permisos);
    setSesion(true);
  };

  const cerrarSesion = async () => {
    await borrarToken();
    await SecureStore.deleteItemAsync(USER_KEY);
    await SecureStore.deleteItemAsync(PERMISOS_KEY);
    setUsuario(null);
    setPermisos([]);
    setSesion(false);
  };

  const tienePermiso = (permiso: string) => permisos.includes(permiso);

  const valor = useMemo(
    () => ({ listo, sesion, usuario, permisos, iniciarSesion, cerrarSesion, tienePermiso }),
    [listo, sesion, usuario, permisos]
  );

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth debe usarse dentro de AuthProvider');
  return ctx;
}
