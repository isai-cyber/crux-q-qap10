# Análisis del proyecto — CMMS Industrial con IA (SIRIUS Maintenance Hub)

## 1. Origen de los requisitos

A diferencia de un encargo típico de "clonar una app desde capturas de pantalla", las
fuentes disponibles son **documentación técnica de dominio**, no una especificación de
software con pantallas/formularios preexistentes:

| Documento | Naturaleza | Qué aporta al proyecto |
|---|---|---|
| `ic21-cppc-06000-0921.pdf` (Siemens Industrial Controls Product Catalog, ene. 2026, 2062 pág.) | Catálogo comercial | Maestro de productos/repuestos: categorías (SIRIUS Control, IEC Power Control, SIRIUS Hybrid, Solid State Control...), números de parte, especificaciones. Fuente de datos para el módulo de **Catálogo/Repuestos**. |
| `BE2_es_es-ES.pdf` (Manual de puesta en marcha SINUMERIK 840D sl / SINUMERIK Operate, 1451 pág.) | Manual de software de control CNC | Terminología, procedimientos de diagnóstico/puesta en marcha, estructura por capítulos (IM7/IM8/IM9/IM10, Run MyScreens) que alimenta la **base de conocimiento (RAG)** para diagnóstico asistido. |
| `SAT_UserGuide_es-ES.pdf` (SIMATIC Automation Tool, 187 pág.) | Manual de herramienta de gestión de dispositivos PLC | Modelo de datos de **inventario de activos**: escaneo de red, IP/nombre PROFINET, firmware, diagnóstico, datos de servicio, memory cards — éste es el documento más cercano a un "manual de software" con flujos de pantalla reales. |
| `840Dsl_PCU_base_commiss_man_es-ES.pdf` (disponible en Desktop, no analizado a fondo aún) | Manual de puesta en marcha PCU | Reserva para ampliar la base de conocimiento CNC en una fase posterior. |
| `toaz.info-automatas-programables...pdf` (libro de Mandado Pérez, 937 pág.) | Libro de texto | Descartado como fuente de requisitos (ver decisión en sección 6). |

Ninguno de los documentos contiene pantallas de una aplicación web a reproducir. Por
tanto, siguiendo la regla del prompt original ("si una característica no está clara, no
inventes arbitrariamente; documenta la ambigüedad y elige la solución técnicamente más
razonable"), el alcance funcional se definió de forma conversacional con el usuario y
quedó registrado aquí como fuente de requisitos formal.

## 2. Decisión de producto

Construir un **CMMS (Computerized Maintenance Management System) ligero para
mantenimiento de automatización industrial**, con:

1. Núcleo CRUD clásico (activos, catálogo de repuestos, tickets, órdenes de trabajo, usuarios).
2. Capacidades de IA generativa integradas vía API HTTP desde PHP (triage de tickets,
   búsqueda semántica del catálogo, RAG sobre manuales, generación de BOM).
3. Un **contrato de API REST** ("herramientas") que un agente autónomo externo tipo
   **Hermes Agent** puede consumir para atender canales (WhatsApp/Slack/email) de forma
   proactiva. Hermes **no se aloja ni se construye dentro de este proyecto** — es un
   servicio externo que el usuario despliega por su cuenta y que consume la API que
   construimos aquí.

## 3. Entidades del dominio (modelo de datos)

- **usuarios** (id, nombre, email, password_hash, rol, planta_id, activo, timestamps)
- **roles** (admin, supervisor, técnico) — permisos por módulo
- **plantas** (sedes/plantas industriales)
- **categorias_producto** (jerarquía del catálogo: SIRIUS Control, IEC Power Control, etc.)
- **productos_catalogo** (número de parte, categoría, descripción, especificaciones JSON,
  precio de lista, vigencia de catálogo, origen: `ic21-cppc-06000-0921`)
- **activos** (equipo/dispositivo físico en planta: nombre, tipo, planta_id, ubicación,
  IP, nombre PROFINET, versión firmware, fecha última puesta en marcha, estado)
- **activo_producto** (relación activo ↔ productos del catálogo que lo componen, para BOM)
- **diagnosticos** (histórico de "datos de servicio" por activo: código, severidad, fecha,
  origen: manual/lectura automática)
- **tickets** (folio, activo_id, reportado_por, canal_origen [web/whatsapp/slack/email],
  descripción, categoría_ia, prioridad_ia, estado, fecha_apertura, fecha_cierre)
- **ordenes_trabajo** (ticket_id, técnico_asignado_id, repuestos_usados, diagnóstico,
  solución aplicada, tiempo invertido, estado)
- **movimientos_stock** (producto_id, tipo [entrada/salida], cantidad, orden_trabajo_id,
  fecha) — con regla de negocio: no permitir stock negativo salvo backorder explícito
- **runbooks** (procedimiento reutilizable: título, síntoma, causa, solución, generado
  por IA o por técnico, activo relacionado, veces aplicado)
- **base_conocimiento_chunks** (fragmentos indexados de los manuales BE2/SAT para RAG:
  documento_origen, capítulo, texto, embedding)
- **api_tokens** (para autenticar a Hermes u otros consumidores de la API REST)
- **logs_ia** (auditoría de cada llamada a IA: endpoint, entrada, salida, costo, fecha)

## 4. Pantallas / módulos a construir

1. Login / gestión de sesión (roles: admin, supervisor, técnico)
2. Dashboard (KPIs: tickets abiertos, activos críticos, stock bajo, SLA)
3. Catálogo de productos — listado, filtros, búsqueda semántica IA, ficha de producto
4. Inventario de activos (CMDB) — listado por planta, alta/edición, historial de diagnóstico
5. Tickets — bandeja, detalle, triage IA visible/editable, asignación
6. Órdenes de trabajo — checklist, repuestos usados, cierre
7. Runbooks — biblioteca de procedimientos, generados por IA o manuales
8. Reportes — tickets por planta/fecha/estado, consumo de repuestos, SLA, exportación PDF/Excel
9. Administración — usuarios/roles, plantas, tokens de API (para Hermes), configuración de IA
10. Panel de integración IA — logs de llamadas IA, estado de la base de conocimiento (RAG)

## 5. Reglas de negocio identificadas

- Un ticket no puede cerrarse sin una orden de trabajo asociada con solución registrada.
- Las salidas de stock por orden de trabajo disminuyen existencias; no se permite
  existencia negativa (excepto marcar explícitamente "pedido pendiente").
- La clasificación de prioridad/categoría hecha por IA es una **sugerencia**: el
  supervisor puede sobreescribirla; ambos valores (IA y humano) se guardan para
  trazabilidad y mejora del modelo.
- Todo endpoint de la API consumido por Hermes requiere `api_token` válido con permisos
  explícitos por acción (crear ticket, consultar activo, consultar catálogo, etc.) —
  nunca acceso irrestricto.
- Un runbook generado automáticamente queda en estado "sugerido" hasta que un técnico lo
  valida al menos una vez; solo entonces se ofrece como sugerencia automática a otros tickets.
- Las llamadas a proveedores de IA externos (LLM) nunca reciben credenciales de usuarios
  ni datos personales innecesarios; solo contexto técnico del ticket/activo.

## 6. Ambigüedades resueltas

- **¿Se usa el libro de Mandado Pérez?** No. Es un libro de texto general de PLCs sin
  relación directa con los activos/catálogo del usuario. Se excluye del alcance para no
  inflar el proyecto con contenido genérico no accionable.
- **¿Se construye/aloja Hermes Agent dentro de este repo?** No. Es una decisión técnica:
  Hermes es un agente autónomo de terceros (Nous Research) pensado para desplegarse como
  servicio independiente (self-host o gestionado) con sus propias credenciales de
  WhatsApp/Slack/email. Este proyecto se limita a exponer una **API REST documentada y
  autenticada** que Hermes (o cualquier otro orquestador) puede consumir como
  "herramientas". Construir el hosting de Hermes está fuera del stack obligatorio
  (PHP/MariaDB/Apache) y fuera de lo que puede verificarse en este entorno.
- **¿Qué proveedor de IA generativa se usa?** Se deja configurable vía `.env`
  (`AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`) para no atar el proyecto a un proveedor;
  el código de la capa de IA se aísla en `app/services/AIService.php` con un adaptador
  simple por proveedor.
- **Vector search**: MariaDB 10.6+ no tiene tipo `VECTOR` nativo (llega en 11.7+). Se
  opta por almacenar embeddings como `JSON`/`LONGTEXT` y calcular similitud coseno en
  PHP para el volumen esperado (cientos–miles de chunks), evitando dependencias externas
  (sin Postgres/pgvector, respetando el stack obligatorio).

## 6.1 Ampliaciones posteriores (2026-08-23)

A petición del usuario se añadieron dos capacidades sobre el diseño original:

- **Diseños técnicos con IA** (`disenos_tecnicos`): genera diagramas de
  control/cableado en SVG y documentos técnicos (Markdown: alcance, BOM,
  procedimiento, seguridad) a partir de una descripción libre, asociables a
  un activo. El SVG generado se sanea en servidor (se eliminan `<script>`,
  `on*` y `<iframe>`) y se renderiza como `<img>` con data URI, no inline,
  para que un navegador nunca ejecute código embebido en el SVG aunque la
  IA sea manipulada vía prompt injection.
- **Autoevolución sin pipeline de entrenamiento**: en vez de reentrenar un
  modelo, el sistema cierra un ciclo de retroalimentación real dentro de
  PHP/MariaDB:
  - `RunbookModel::registrarAplicacion()` registra cada resultado real
    (éxito/fallo) de aplicar un runbook a un ticket. Con ≥3 aplicaciones y
    ≥70% de éxito el runbook se **valida** solo; con ≥50% de fallo se
    **retira** (`vigente=0`) y deja de sugerirse. Verificado end-to-end con
    datos reales (ver `docs/PLAN.md`).
  - Al completar una orden de trabajo sin runbook seleccionado pero con una
    solución escrita, se **crea un runbook nuevo automáticamente**
    (`RunbookModel::crearDesdeTicket()`).
  - `AIService::triageTicket()` incluye como ejemplos few-shot los últimos
    tickets que un supervisor corrigió (`categoria_final`/`prioridad_final`),
    así que el criterio de clasificación se alinea solo con el humano sin
    tocar código ni reentrenar nada.
  - Se documenta esta decisión explícitamente porque "autoevolutivo" es
    ambiguo: se optó por reglas de negocio + few-shot dinámico (verificable,
    determinista, auditable vía `logs_ia`) en vez de fine-tuning de modelos,
    que no encaja en el stack obligatorio PHP/MariaDB/Apache.
- **Mapa de conocimiento 3D** (`/conocimiento`): visualización con
  `3d-force-graph` (JS puro vía CDN, sin frameworks) de cómo se conectan
  activos, tickets, runbooks, diseños técnicos y documentos fuente de la
  base de conocimiento — pensado para hacer visible el grafo que alimenta
  las sugerencias de IA. Nodos con halo luminoso (glow) sobre fondo blanco,
  con una paleta de colores vivos por tipo de nodo.
  - **Nota técnica**: las versiones recientes de `three.js` (≥0.170) ya no
    publican un build UMD global (`three.min.js`); solo ES modules. Cargar
    `<script src>` clásico causaba `THREE is not defined` y fallos en
    cascada. Se resolvió importando todo como ES modules vía `esm.sh`, que
    resuelve la dependencia `three` automáticamente y permite fijar la misma
    versión para `3d-force-graph` y el `three` usado en el halo personalizado
    (`?deps=three@0.180.0`), evitando conflictos de versiones.
  - El halo se logra con un sprite de textura (degradado radial generado en
    `<canvas>`) en mezcla normal (no aditiva): la mezcla aditiva "lava" el
    color contra un fondo blanco y el halo desaparece, por eso se usó alpha
    blending normal con opacidad decreciente en dos capas (interior/exterior).
- **Rediseño visual**: paleta clara (a petición explícita del usuario, que
  rechazó una primera versión con sidebar oscuro), tipografía Inter,
  Bootstrap Icons, tarjetas con sombra suave y acentos en gradiente
  índigo/violeta solo para estados activos/CTAs.

## 6.2 Chat interno en vez de WhatsApp (2026-08-23)

A petición del usuario, el canal conversacional de reporte de fallas se
implementó como un **chat dentro de la propia plataforma** (`/chat`,
`ChatController`, AJAX/fetch) en vez de integrarse con WhatsApp Business API.

- Motivo: WhatsApp requiere credenciales de Meta Business que el usuario no
  tiene disponibles en este entorno; un chat interno logra el mismo
  resultado funcional (reporte conversacional → triage IA → ticket →
  sugerencia de runbook) sin esa dependencia externa.
- Cada mensaje se trata como un **reporte de falla independiente** (mismo
  comportamiento que tendría Hermes recibiendo un mensaje de WhatsApp): se
  crea un ticket, se clasifica con `AIService::triageTicket()` y se busca un
  runbook aplicable con `RunbookModel::sugerirPara()`. No hay detección de
  intención conversacional (seguimiento, cancelación, etc.) — para dar
  seguimiento a un ticket ya abierto se usa el módulo Tickets normal. Se
  documenta como decisión explícita para no inflar el alcance con un router
  de intenciones que no se pidió.
- `tickets.canal_origen` ganó el valor `'chat'` junto a los existentes
  (`web`, `whatsapp`, `slack`, `email`, `api`) — los canales externos
  (WhatsApp/Slack) siguen disponibles vía la API REST (`docs/API.md`) para
  quien despliegue Hermes u otro agente más adelante; el chat interno no los
  reemplaza en el modelo de datos, solo ofrece una alternativa que no
  depende de configurarlos.
- Verificado end-to-end: mensaje → ticket TCK-2026-0005 creado → clasificado
  como "crítica" → runbook validado sugerido automáticamente → historial
  persiste entre recargas de página.

## 6.3 Multi-tenencia, MCP, ORION y RAG (2026-08-23)

A petición del usuario ("es para un micro SaaS"), el sistema se convirtió de
una sola empresa a **multi-tenant**: múltiples organizaciones (clientes)
aisladas dentro de la misma base de datos.

- **Qué es global vs. por organización**: `productos_catalogo`,
  `categorias_producto` y `base_conocimiento_chunks` son GLOBALES (datos de
  referencia del fabricante/manuales, iguales para todos los clientes). Todo
  lo demás (usuarios, plantas, activos, tickets, runbooks, inventario,
  diseños técnicos, tokens de API, chat, logs de IA) tiene `organizacion_id`
  y se filtra siempre por la organización del usuario/token autenticado.
  Decisión documentada porque no era obvia: el stock físico de repuestos
  (antes una columna en `productos_catalogo`) se separó a una tabla
  `inventario` propia de cada organización, porque el catálogo es
  compartido pero el stock de cada empresa no lo es.
- **Alta de organizaciones**: registro público en `/registro`
  (`RegistroController`) — sin esto solo la organización sembrada por
  `seed.sql` podría usar el sistema. Crea la organización, una planta
  "Planta principal" y el primer usuario admin en una transacción.
- **Aislamiento verificado en pruebas**: se creó una segunda organización
  real y se confirmó que ve 0 activos/tickets de la primera, comparte el
  catálogo Siemens (con stock independiente en 0), y no puede editar activos
  de la otra organización (404, no fuga de datos).
- **Bug real encontrado y corregido**: el folio de tickets (`TCK-2026-0001`)
  tenía una restricción `UNIQUE` global, pero se numera por organización →
  dos organizaciones generaban el mismo folio y la segunda fallaba al
  crear su primer ticket. Se corrigió a `UNIQUE (organizacion_id, folio)`.

**ORION** es el nombre propio del asistente del chat interno
(`ChatController::NOMBRE_ASISTENTE`), con una persona compartida en todos
los prompts de IA (`AIService::PERSONA`) que lo ancla al nicho de
automatización industrial/mantenimiento en vez de ser un asistente
genérico.

**Gestión de usuarios desde el chat**: un admin puede escribirle a ORION
"crear usuario: Nombre, email@empresa.com, tecnico[, contraseña]" y da de
alta el usuario sin salir del chat (mismo camino disponible vía API REST y
MCP). Decisión de alcance: no se construyó un router de intenciones NLU
genérico — se detecta la intención por palabras clave (crear/agregar +
usuario/técnico/rol) y, sin IA configurada, se exige ese formato
estructurado; con IA configurada, `AIService::extraerDatosUsuario()` es más
tolerante a redacciones libres.

**RAG sobre manuales**: `AIService::responderConManuales()` recupera
fragmentos de `base_conocimiento_chunks` por coincidencia de palabras clave
(funciona sin generar embeddings) y, si hay `AI_API_KEY`, sintetiza una
respuesta citando la fuente; sin IA, devuelve los fragmentos tal cual. Se
sembró una **muestra real** (`database/seed_conocimiento.sql`) extraída
directamente de BE2 (SINUMERIK) y SAT (SIMATIC Automation Tool) durante el
análisis del proyecto, para que el flujo sea comprobable de punta a punta.
**Límite documentado**: no se ingirieron los ~1600 páginas completas de
ambos manuales — eso requiere generar embeddings reales (con `AI_API_KEY`)
y es trabajo aparte; ampliar la cobertura es cuestión de sembrar más chunks,
no de cambiar código.

**Servidor MCP** (`/mcp`, `McpController`, ver `docs/MCP.md`): protocolo
JSON-RPC 2.0 implementado en PHP puro (sin SDK de Node/Python, respetando
el stack obligatorio), exponiendo las mismas 6 herramientas que la API REST
para que Hermes Agent, Claude u otro cliente MCP operen el CMMS. Probado
end-to-end: `initialize`, `tools/list`, `tools/call` con y sin token.

## 7. Fuera de alcance (explícito)

- Escaneo real de redes PROFINET/actualización de firmware de PLCs desde la web app
  (function del SIMATIC Automation Tool original) — es una operación de bajo nivel que
  requiere acceso a red industrial (OT), no apta para un backend PHP en Apache por
  motivos de seguridad y de arquitectura. El sistema **registra** esos datos (IP,
  firmware, diagnóstico) pero no ejecuta las operaciones de red en sí.
- Hosting/despliegue de Hermes Agent (ver sección 6).
- Integraciones reales con WhatsApp Business API / Slack API: se documenta el contrato
  de API que las consumiría, pero activar las credenciales reales es responsabilidad del
  usuario.
