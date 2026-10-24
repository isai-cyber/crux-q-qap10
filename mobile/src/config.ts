/**
 * Configuración global de la app.
 *
 * EXPO_PUBLIC_API_URL se lee en tiempo de bundle (Expo):
 *  - Emulador Android: http://10.0.2.2:8090 (10.0.2.2 = localhost del host)
 *  - Dispositivo físico: http://<IP-LAN-de-tu-Mac>:8090 (php -S 0.0.0.0:8090)
 *  - Producción: https://tu-dominio.com
 */
export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ?? 'http://10.0.2.2:8090';
