# API REST — Contrato de integración de agentes

Esta API permite que un agente de automatización externo (un bot de WhatsApp/Slack, u
otro orquestador) opere el CMMS como "herramientas", sin acceso directo a la base de datos.

## Autenticación

Todas las rutas requieren un header:

```
Authorization: Bearer <token>
```

El token se genera desde **Administración → Tokens de API** (`/admin/tokens`), con
permisos explícitos por acción. Los tokens se almacenan hasheados (SHA-256); el valor en
claro solo se muestra una vez al crearlo.

**Login desde la app móvil:** `POST /api/v1/auth/login` acepta `{usuario, password,
nombre_dispositivo?}` y devuelve un token Bearer con permisos según el rol del usuario
(admin/supervisor: todos; técnico: consulta/actualización de tickets, activos, catálogo,
alertas y chat). Cada nuevo login con el mismo `nombre_dispositivo` revoca el token
anterior de ese dispositivo.

## Permisos

| Permiso | Otorga |
|---|---|
| `crear_ticket` | Crear tickets con triage IA |
| `consultar_activos` | Ver activos (lista y detalle con diagnósticos) |
| `crear_activo` | Dar de alta activos |
| `consultar_catalogo` | Buscar repuestos |
| `consultar_alertas` | Alertas críticas y sin stock |
| `crear_usuario` | Alta de usuarios |
| `consultar_tickets` | Listar tickets y ver el dashboard |
| `actualizar_ticket` | Cambiar estado de tickets |
| `usar_chat` | Conversar con ORION |

> Nota: los tokens creados antes de la app móvil no tienen los tres últimos permisos;
> revísalos o genera uno nuevo si obtienes `403`.

## Endpoints

### `POST /api/v1/tickets`
Permiso requerido: `crear_ticket`

Crea un ticket y lo clasifica automáticamente con IA (categoría/prioridad).

```json
// Request
{
  "descripcion": "El contactor de la línea 2 no arranca",
  "activo_nombre": "Contactor L2",
  "canal_origen": "whatsapp"
}

// Response 201
{
  "ticket": { "id": 12, "folio": "TCK-2026-0012", "estado": "abierto", "...": "..." },
  "triage": { "categoria": "electrico", "prioridad": "critica", "razon": "..." }
}
```

### `GET /api/v1/activos/{id}`
Permiso requerido: `consultar_activos`

Devuelve los datos del activo y su historial de diagnósticos.

### `POST /api/v1/activos`
Permiso requerido: `crear_activo`

Da de alta un activo (por ejemplo, tras extraer modelo/serie de una foto con visión IA
en el agente externo). Body: mismos campos que el formulario web (`nombre`, `planta_id`,
`tipo`, `ubicacion`, `ip_address`, `nombre_profinet`, `version_firmware`, `estado`).

### `GET /api/v1/catalogo/buscar?q=texto`
Permiso requerido: `consultar_catalogo`

Búsqueda por número de parte o descripción. Devuelve hasta 20 resultados.

### `GET /api/v1/alertas/pendientes`
Permiso requerido: `consultar_alertas`

Pensado para que el agente haga polling periódico (vigilancia proactiva): devuelve
tickets críticos sin atender y productos sin stock.

## Endpoints de la app móvil

### `POST /api/v1/auth/login` (público, sin token)
Body: `{ "usuario": "...", "password": "...", "nombre_dispositivo": "Pixel 7" }`

Response 201: `{ "token": "crux_...", "usuario": {id, nombre, usuario, email, rol}, "organizacion_id": 1, "permisos": [...] }`
Credenciales inválidas → 401. El nombre de dispositivo es opcional; si se repite, revoca
el token anterior de ese dispositivo.

### `GET /api/v1/me`
Permiso: ninguno (cualquier token válido). Devuelve el usuario asociado al token
(id, nombre, usuario, email, rol) y su `organizacion_id`.

### `GET /api/v1/tickets?estado=&page=`
Permiso: `consultar_tickets`. Lista paginada: `{items, total, page, per_page}`.

### `GET /api/v1/tickets/{id}`
Permiso: `consultar_tickets`. Detalle con `activo_nombre`. 404 si no existe o es de otra organización.

### `POST /api/v1/tickets/{id}/estado`
Permiso: `actualizar_ticket`. Body: `{ "estado": "abierto|en_progreso|esperando_repuesto|cerrado|cancelado" }`.
Devuelve el ticket actualizado. Estado inválido → 422.

### `GET /api/v1/activos?q=&page=`
Permiso: `consultar_activos`. Lista paginada con búsqueda opcional.

### `POST /api/v1/chat`
Permiso: `usar_chat`. Body: `{ "mensaje": "...", "activo_id": 3? }` (máx. 2000 caracteres).
Mismo comportamiento que el chat web con ORION: reporte de falla (crea ticket con triage IA),
consulta de manuales (RAG) o alta de usuarios (solo admin). Response: `{ "respuesta": "...", "ticket": {id, folio} | null }`.

### `GET /api/v1/dashboard`
Permiso: `consultar_tickets`. KPIs + últimos 8 tickets, mismo dato que el dashboard web.

## Notas de seguridad

- Ningún endpoint acepta SQL ni HTML sin sanear; todas las consultas usan PDO preparado.
- Los tokens son de un solo permiso mínimo necesario: crear varios tokens con alcance
  reducido en vez de uno con todos los permisos.
- Revocar un token (`/admin/tokens/{id}/revocar`) lo invalida de inmediato.
- Esta API **no** ejecuta operaciones de red industrial (escaneo PROFINET, actualización
  de firmware); solo registra/consulta datos ya conocidos por el CMMS. Ver
  `docs/ANALISIS.md` §7 para el detalle de qué queda fuera de alcance.

## Lo que este proyecto NO incluye

- El hosting/despliegue de agentes de mensajería externos (WhatsApp/Slack) — es
  responsabilidad del usuario, con sus propias credenciales de esos canales.
- Credenciales de proveedor de IA generativa (`AI_API_KEY` en `.env`) — deben ser
  provistas por el usuario para que el triage/RAG funcionen con un modelo real (sin
  ellas, el sistema usa una heurística de respaldo, ver `AIService::triageFallback`).
