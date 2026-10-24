# Plan del proyecto — CMMS Industrial con IA

Convenciones: `[ ]` Pendiente · `[x]` Completado · `[~]` En progreso / parcial

## Fase 1 — Análisis del PDF y del dominio
- [x] Analizar los 4 documentos disponibles y clasificar su naturaleza
- [x] Definir decisión de producto (CMMS + IA + contrato API para Hermes)
- [x] Documentar entidades, pantallas, reglas de negocio y ambigüedades → `docs/ANALISIS.md`

## Fase 2 — Arquitectura
- [x] Estructura de carpetas (`app/`, `public/`, `views/`, `database/`, `docs/`, `storage/`)
- [x] `app/config/config.php` (config) + `app/helpers/env.php` (carga de `.env`)
- [x] `app/config/database.php` (conexión PDO)
- [x] Router simple en `public/index.php`
- [x] `.htaccess` (public/ y raíz)
- [x] `.env.example`

## Fase 3 — Base de datos MariaDB
- [x] `database/schema.sql` — probado contra MariaDB 12.3 real: 15 tablas, FKs e índices OK
- [x] `database/seed.sql` — probado: usuario admin, 1 planta, 4 categorías, 3 productos
- [ ] Carga real de una muestra mayor del catálogo Siemens (extracción asistida de más páginas del PDF; por ahora solo 3 productos de ejemplo)

## Fase 4 — Autenticación y control de acceso
- [x] Login/logout con `password_hash`/`password_verify` — probado con usuario real
- [x] Sesiones PHP seguras (regeneración de ID, cookies httponly/samesite)
- [x] Middleware de autenticación y de rol — probado: técnico bloqueado con 403 en `/admin/usuarios` y `/activos/nuevo`
- [x] CSRF tokens en formularios — probado (rechaza sin token válido)

## Fase 5 — Layout principal y navegación
- [x] `views/layouts/header.php` + `footer.php` (Bootstrap 5 vía CDN, responsive)
- [x] Menú lateral por rol (oculta "Administración" a no-admin)
- [ ] Alertas/notificaciones tipo toast (por ahora solo `alert` de Bootstrap en formularios)

## Fase 6 — Dashboard
- [x] KPIs: tickets abiertos, tickets críticos, activos fuera de servicio, productos sin stock
- [ ] Gráficos (se dejó solo tabular por ahora; sin librería de gráficos aún)

## Fase 7 — Módulos principales (CRUD)
- [x] Plantas (vía seed) y usuarios (admin) — alta y activar/desactivar probado
- [x] Catálogo de productos (listar/buscar/filtrar) — CRUD de escritura queda para fase posterior (solo lectura por ahora)
- [x] Activos / CMDB (crear, ver, editar, eliminar, historial de diagnóstico) — CRUD completo probado
- [x] Tickets (crear con triage IA automático, listar, detalle, cambiar estado) — probado end-to-end
- [x] Órdenes de trabajo — se crean automáticamente al abrir un ticket; edición y cierre probados
- [x] Movimientos de stock — `ProductoModel::registrarMovimientoStock()` con transacción y regla de no-negativo (aún sin UI dedicada)
- [ ] Runbooks — vista de listado lista; falta UI de creación/edición

## Fase 8 — Capa de IA (dentro de la app PHP)
- [x] `app/services/AIService.php` (adaptador HTTP OpenAI/Anthropic vía `.env`, con heurística de respaldo)
- [x] Triage de tickets — probado end-to-end (clasificó "no arranca" como prioridad crítica sin `AI_API_KEY`)
- [ ] Indexado de manuales BE2/SAT en `base_conocimiento_chunks` (tabla lista, falta el script de ingesta)
- [ ] Búsqueda semántica del catálogo (columna `embedding` lista, falta generarlos e implementar similitud coseno)
- [ ] RAG: diagnóstico sugerido + runbook sugerido en detalle de ticket
- [ ] Generador de BOM conversacional
- [x] `logs_ia` (tabla y registro de llamadas implementados)

## Fase 9 — API REST para integración con Hermes / terceros
- [x] Contrato documentado en `docs/API.md`
- [x] `POST /api/v1/tickets` — probado con token real (201, triage aplicado)
- [x] `GET /api/v1/activos/{id}` — probado (200)
- [x] `POST /api/v1/activos` — implementado y protegido por permiso (403 si falta, probado)
- [x] `GET /api/v1/catalogo/buscar` — implementado
- [x] `GET /api/v1/alertas/pendientes` — probado (devuelve tickets críticos + stock agotado)
- [x] Gestión de tokens desde `/admin/tokens` (generar/revocar) — probado end-to-end
- [ ] Rate limiting básico (aún no implementado)

## Fase 10 — Reportes
- [x] Tickets por estado y por planta
- [x] Consumo de repuestos (top 10)
- [ ] Cumplimiento de SLA (falta definir umbral de SLA)
- [x] Exportación CSV — probado; corregido bug de `fputcsv()` (deprecación de PHP 8.5 por `$escape` implícito)
- [ ] Exportación PDF

## Fase 11 — Seguridad
- [x] 100% PDO preparado (sin concatenación de SQL con datos de usuario)
- [x] `htmlspecialchars` vía helper `e()` en toda salida dinámica de las vistas
- [x] CSRF en formularios de escritura — probado
- [x] Validación server-side (activos, usuarios, tickets)
- [ ] Subida segura de archivos (fotos de activos) — aún no implementada (no hay upload de fotos todavía)
- [x] Protección de rutas por rol en cada controlador — probado (no solo oculto en el menú)

## Fase 12 — Pruebas
- [x] CRUD de activos probado end-to-end contra MariaDB real (crear, ver, listar)
- [x] Permisos probados (403 en rutas admin/creación para rol técnico)
- [x] Flujo IA probado con heurística de respaldo (sin `AI_API_KEY`); pendiente probar con proveedor real
- [x] Endpoints API probados con cURL simulando a Hermes (crear ticket, consultar activo, alertas, rechazo sin token, rechazo sin permiso)
- [x] `php -l` sin errores de sintaxis en todos los archivos

## Fase 13 — Optimización
- [ ] Índices adicionales según consultas de reportes reales
- [x] Paginación en catálogo, activos y tickets
- [ ] Cacheo de búsquedas semánticas frecuentes

## Fase 14 — Instalación
- [x] `README.md` con pasos completos
- [x] `docs/API.md`

## Fase 15 — Preparación para producción
- [ ] Checklist de hardening final (display_errors off, revisar permisos de `storage/`)
- [ ] Revisión final de botones/formularios sin funcionalidad (catálogo, runbooks y movimientos de stock aún sin UI de escritura)

## Fase 16 — Diseños técnicos IA, autoevolución y mapa de conocimiento (2026-08-23)
- [x] `disenos_tecnicos` (schema.sql + migrations.sql) — probado: generación con fallback (sin `AI_API_KEY`), sanitizado de SVG, render como `<img>` data-URI
- [x] `AIService::generateDiagram()` / `generateTechDoc()` con fallback claro cuando no hay proveedor configurado
- [x] `runbooks.vigente/veces_exitoso/veces_fallido` + tabla `runbook_aplicaciones`
- [x] `RunbookModel::registrarAplicacion()` — probado con datos reales: 3 éxitos → `validado=1` automático; 2 fallos de 3 → `vigente=0` automático
- [x] `RunbookModel::crearDesdeTicket()` — probado: al completar una orden sin runbook seleccionado, se crea uno nuevo solo
- [x] `AIService::triageTicket()` con few-shot dinámico de tickets corregidos por humanos (`obtenerEjemplosValidados()`)
- [x] Sugerencia de runbook en la orden de trabajo (`RunbookModel::sugerirPara()`) — heurística de palabras clave; sustituible por embeddings sin cambiar la interfaz
- [x] `/conocimiento` — grafo 3D (`3d-force-graph` vía CDN) de activos/tickets/runbooks/diseños/documentos fuente — probado visualmente con datos reales
- [x] Rediseño visual completo (paleta clara, Inter, Bootstrap Icons, sidebar con estado activo) — iterado tras feedback del usuario (primera versión con sidebar oscuro fue rechazada)
- [x] Grafo 3D: nodos con halo luminoso (glow) sobre fondo blanco — resuelto el conflicto de `three.js` (ya no publica UMD global) migrando a ES modules vía esm.sh

## Fase 17 — Chat interno (reemplaza WhatsApp) (2026-08-23)
- [x] `chat_mensajes` (schema.sql + migrations.sql) + `tickets.canal_origen` con valor `'chat'`
- [x] `ChatController` (AJAX/fetch, sin recarga de página) — probado end-to-end: mensaje → ticket clasificado → runbook validado sugerido → historial persistente
- [x] Bug encontrado y corregido en pruebas: el JS limpiaba el input antes de leer `FormData`, enviando el mensaje vacío al servidor

## Fase 18 — Multi-tenencia (micro SaaS), MCP, ORION y RAG (2026-08-23)
- [x] `organizaciones` + `organizacion_id` en usuarios/plantas/activos/tickets/ordenes/runbooks/disenos/api_tokens/chat_mensajes/logs_ia/movimientos_stock; catálogo y base de conocimiento quedan globales/compartidos
- [x] `inventario` (stock por organización, separado del catálogo global) — `ProductoModel::registrarMovimientoStock()` migrado con upsert
- [x] `/registro` (`RegistroController`) — alta de organización + admin en una transacción; probado creando una segunda organización real
- [x] **Aislamiento verificado**: la segunda organización ve 0 activos/tickets de la primera, catálogo compartido con stock en 0, 404 al intentar acceder a un activo de otra organización
- [x] Bug real corregido: `UNIQUE(folio)` global chocaba entre organizaciones → `UNIQUE(organizacion_id, folio)`
- [x] Bug real corregido: `ActivoModel::update()` no enviaba el parámetro `fecha_puesta_marcha` que la consulta SQL requería (habría fallado siempre al editar un activo) — encontrado y corregido en esta pasada de pruebas, no introducido por la multi-tenencia
- [x] Varios usos de `?:` sobre claves de array potencialmente ausentes cambiados a `??` (warnings reales de PHP 8.5 detectados en pruebas con MCP/chat, que envían payloads parciales)
- [x] `ChatController::NOMBRE_ASISTENTE = 'ORION'` + `AIService::PERSONA` (contexto de automatización industrial compartido por todos los prompts)
- [x] Gestión de usuarios desde el chat (solo admin) — probado: creación exitosa, bloqueo correcto para rol técnico, extracción con IA + fallback por formato estructurado sin IA
- [x] `AIService::responderConManuales()` (RAG por palabras clave + síntesis opcional con IA) — probado con datos reales sembrados de BE2/SAT
- [x] `database/seed_conocimiento.sql` — muestra real de contenido de BE2 y SAT (no la ingesta completa; documentado como límite de alcance)
- [x] Servidor MCP (`/mcp`, `McpController`) en PHP puro (JSON-RPC 2.0, sin SDK de Node/Python) — 6 herramientas, probado end-to-end (`initialize`, `tools/list`, `tools/call` con/sin token) — ver `docs/MCP.md`
- [x] `crear_usuario` disponible también vía API REST y MCP, no solo chat

## Fase 19 — Seguridad de facturación, login por usuario y whitelabel (2026-09-08)
- [x] **Bug crítico corregido**: `/suscripcion/activar-enterprise` (y `cancelar`/`reembolso`/`portal`) solo pedían sesión iniciada, sin rol ni CSRF en el GET — cualquier usuario, incluso técnico, podía auto-activar el plan Enterprise ($997) gratis. Ahora requieren rol admin, el endpoint pasó a POST+CSRF, y `exito()` verifica el pago contra la API de Stripe cuando hay `STRIPE_SECRET_KEY` configurada (antes confiaba ciegamente en el `session_id` del query string) — probado end-to-end: técnico bloqueado con 403 server-side, admin funciona normal
- [x] Login por `usuario` en vez de `email` (columna `usuarios.usuario`, única por organización) — aplica a login web, app móvil (`/api/v1/auth/login`), registro y alta de usuarios en Administración; ORION/API/MCP siguen aceptando solo nombre+email y derivan un usuario automáticamente — probado con 3 cuentas reales
- [x] Landing pública eliminada (`LandingController` y `views/landing/` borrados) — `/` y `/login` van directo al formulario de login (o al dashboard si hay sesión); `/landing` da 404
- [x] Whitelabel: cada organización sube su logo desde `/admin/organizacion` (solo admin) — reemplaza el logo y nombre de Crux A.I en sidebar, header, título de pestaña y `/login?org=slug`; probado con logo real subido y aislamiento correcto entre organizaciones
- [ ] El nombre de marca en `/login` sin `?org=slug` sigue siendo genérico "Crux A.I" (no hay selector de organización antes del login)

## Fase 20 — Onboarding de instalación y ORION como cliente MCP (2026-09-08)
- [x] Clave de IA configurable por organización (`organizaciones.ai_provider/ai_api_key/ai_model`, `/admin/organizacion`) — antes era una sola clave global en `.env` compartida por todos los tenants; ahora cada organización puede traer la suya, con el `.env` como respaldo. Bug encontrado y corregido en pruebas: dejar el campo en blanco borraba la clave guardada en vez de conservarla (el texto del formulario prometía lo contrario) — ahora blanco = no tocar, checkbox explícito "Quitar la clave" para borrarla
- [x] `/registro` redirige al onboarding (`/admin/organizacion?onboarding=1`) en vez del dashboard — pasos: logo, clave de IA, alta de equipo (enlaza a `/admin/usuarios`)
- [x] ORION como **cliente** MCP (no solo servidor): conecta servidores MCP externos del mismo nicho desde el chat — `McpClientService` (JSON-RPC 2.0 por HTTP), `McpConexionModel`, tabla `mcp_conexiones` por organización. Comandos por chat (solo admin): "conectar mcp: nombre, url[, token]", "listar conexiones mcp", "desconectar mcp: nombre". Si el mensaje menciona una herramienta ya conectada por su nombre, ORION extrae los argumentos (con IA si está configurada, si no un heurístico simple) y la invoca — probado end-to-end contra un servidor MCP mock: conectar → descubrir herramienta → invocarla desde una frase natural → desconectar
- [x] Bug real encontrado y corregido en pruebas: `curl_close()` (deprecado en PHP 8.5, sin efecto desde PHP 8.0) generaba warnings en `McpClientService` y `StripeService`
- [ ] Sin backoff/retry ni caché de errores de conexión — si un servidor MCP externo está caído, cada mención de sus herramientas vuelve a intentar la llamada HTTP (hasta 15s de timeout) en vez de fallar rápido

## Fase 21 — Mantenimiento preventivo programado (2026-09-09)
Primera funcionalidad portada de un CMMS de referencia (Atlas CMMS / Grashjs, revisado en GitHub —
stack distinto en Java/React, así que se adaptó el concepto a este stack, no se copió código).
El sistema era 100% reactivo hasta ahora (solo tickets por falla reportada); esto agrega tareas
recurrentes por calendario.
- [x] `planes_mantenimiento` (activo, título, frecuencia en días, próxima fecha) + `tickets.tipo_mantenimiento` ('correctivo'/'preventivo') + `canal_origen` = 'sistema' para los autogenerados
- [x] `/mantenimiento-preventivo` (crear/listar/desactivar planes, admin y supervisor)
- [x] Generación automática: `PlanMantenimientoModel::generarPendientes()` revisa vencidos y crea ticket + orden de trabajo (reusa `TicketModel::create`), reprograma la próxima fecha sumando la frecuencia. Idempotente por día (no duplica si se recarga la página varias veces el mismo día)
- [x] Dos formas de disparo: perezosa (al visitar `/mantenimiento-preventivo`, funciona sin cron) y `scripts/generar_preventivos.php` para quien prefiera cron — probado end-to-end: plan con fecha vencida → ticket generado automáticamente al cargar la página → orden de trabajo creada → próxima fecha reprogramada → sin duplicar al recargar → script CLI confirma 0 generados (ya generado ese día)
- [ ] Pendiente (fuera de esta pasada): recurrencia por uso/horas de operación (Atlas CMMS lo soporta; este proyecto no tiene telemetría de uso de activos), notificación cuando un plan está por vencer, reportes de cumplimiento preventivo vs correctivo

## Fase 22 — Órdenes de compra con aprobación (2026-09-09)
Segunda funcionalidad portada de Atlas CMMS. Antes el reabastecimiento era un registro directo de
movimiento de stock sin ningún control; ahora hay un flujo formal de 4 estados.
- [x] `ordenes_compra` (folio, producto, cantidad, estado: pendiente/aprobada/rechazada/recibida, solicitante, aprobador)
- [x] `/ordenes-compra` — cualquier usuario logueado puede solicitar; solo admin/supervisor aprueba, rechaza o marca recibida
- [x] El stock **solo** se actualiza al marcar "recibida" (reusa `ProductoModel::registrarMovimientoStock()`) — aprobar por sí solo no toca inventario
- [x] Bug real encontrado y corregido antes de probar: `OrdenCompraModel::recibir()` envolvía la llamada a `registrarMovimientoStock()` (que ya abre su propia transacción) en OTRA transacción — PDO no soporta transacciones anidadas, habría lanzado excepción en cada recepción. Se quitó la transacción externa
- [x] Probado end-to-end: solicitar (pendiente) → aprobar (stock sigue en 0) → recibir (stock pasa a 20, se crea el movimiento con el folio de la orden en el motivo, la orden queda "recibida")

## Fase 23 — Costos por orden y tiempo de inactividad por activo (2026-09-09)
Tercera y última funcionalidad de esta ronda portada de Atlas CMMS.
- [x] `ordenes_trabajo.costo_mano_obra` (captura manual) + costo de repuestos calculado al vuelo desde `orden_trabajo_repuesto` × `productos_catalogo.precio_lista` — sin duplicar el dato, se ve en el detalle de cada orden
- [x] `activos_downtime`: `ActivoModel::update()` ahora abre un registro cuando el estado pasa a "fuera_servicio" y lo cierra cuando sale de ahí — historial real de tiempo de inactividad, no solo el estado actual. Ficha del activo muestra horas acumuladas; Reportes muestra el ranking de activos con más inactividad
- [x] Reportes: nueva sección de costos acumulados (mano de obra + repuestos + total) además del ranking de inactividad
- [x] **Bug real preexistente encontrado y corregido** (no introducido en esta pasada, ya estaba en `ActivoModel::create()`/`update()`): al editar un activo dejando "fecha de puesta en marcha" vacía, se enviaba `''` en vez de `NULL` a una columna `DATE`, y MySQL en modo estricto lo rechazaba con error 500 — esto afectaba **cualquier** edición normal de activo sin esa fecha, no solo el cambio de estado. Se corrigió convirtiendo `''` a `null` explícitamente
- [x] Probado end-to-end: activo operativo → fuera_servicio (abre downtime) → operativo otra vez (cierra downtime, calcula duración) → horas visibles en la ficha del activo y en Reportes; costo de mano de obra capturado en una orden y reflejado en el total de Reportes

---

### Verificación realizada en esta iteración (2026-08-23)
Se levantó MariaDB local + servidor embebido de PHP y se probó en vivo:
schema/seed, login, CSRF, control de acceso por rol, alta de activo, creación de
ticket con triage IA (heurística), auto-creación de orden de trabajo, catálogo,
reportes + export CSV, y la API REST completa (token con permisos, 401 sin token,
403 sin permiso). Se encontraron y corrigieron 2 bugs reales durante la prueba:
redeclaración de `env()`/`env_load()` por `require` no idempotente, y deprecación
de `fputcsv()` en PHP 8.5 por `$escape` implícito.

### Notas de alcance (ver `docs/ANALISIS.md` §7)
- No se implementa escaneo real de red PROFINET ni actualización de firmware desde la web.
- No se aloja Hermes Agent; solo se construye la API que consumiría.
- Activar WhatsApp/Slack reales requiere credenciales propias del usuario.
