# Servidor MCP — SIRIUS Maintenance Hub

El sistema expone un servidor **MCP (Model Context Protocol)** en PHP puro
(`app/controllers/McpController.php`), sin dependencias de Node/Python: MCP
es solo JSON-RPC 2.0 sobre HTTP, así que se implementó directamente con el
stack obligatorio del proyecto (PHP/MariaDB/Apache).

Esto permite que agentes de automatización compatibles con MCP operen el CMMS
como un conjunto de herramientas, exactamente igual que la API REST
(`docs/API.md`), pero hablando el protocolo estándar que ya entienden los
agentes de IA modernos en vez de un contrato REST ad-hoc.

## Endpoint

```
POST /mcp
Content-Type: application/json
Authorization: Bearer <token>   (solo requerido para tools/call)
```

El token es el mismo que se genera en **Administración → Tokens de API**
(tabla `api_tokens`), con permisos explícitos por herramienta.

## Métodos soportados

### `initialize`
Sin autenticación. Devuelve la versión del protocolo y las capacidades del servidor.

### `tools/list`
Sin autenticación. Devuelve las 12 herramientas disponibles con su `inputSchema` (JSON Schema).

### `tools/call`
Requiere `Authorization: Bearer <token>` con el permiso correspondiente a la herramienta.

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "crear_ticket",
    "arguments": { "descripcion": "El contactor de la línea 2 no arranca" }
  }
}
```

## Herramientas expuestas

| Herramienta | Permiso requerido | Qué hace |
|---|---|---|
| `crear_ticket` | `crear_ticket` | Crea un ticket, lo clasifica con IA y sugiere un runbook si existe uno vigente |
| `consultar_activo` | `consultar_activos` | Devuelve un activo y su historial de diagnósticos |
| `crear_activo` | `crear_activo` | Da de alta un equipo en el CMDB |
| `crear_usuario` | `crear_usuario` | Crea un usuario (admin/supervisor/técnico) — misma capacidad que "crear usuario" desde el chat con ORION |
| `buscar_catalogo` | `consultar_catalogo` | Busca productos en el catálogo Siemens |
| `alertas_pendientes` | `consultar_alertas` | Tickets críticos sin atender + productos sin stock (para vigilancia proactiva) |
| `listar_tickets` | `consultar_tickets` | Lista tickets con filtro opcional por estado y paginación |
| `consultar_ticket` | `consultar_tickets` | Detalle de un ticket por ID |
| `cambiar_estado_ticket` | `actualizar_ticket` | Cambia el estado de un ticket (abierto → en_progreso → cerrado, etc.) |
| `listar_activos` | `consultar_activos` | Lista activos con búsqueda opcional por nombre |
| `resumen_dashboard` | `consultar_tickets` | KPIs, distribución por estado/día/prioridad y tasa de cierre |
| `uso_ia` | `consultar_tickets` | Consumo de IA: llamadas, tokens y latencia media por endpoint |

Todas las herramientas quedan aisladas a la **organización del token** usado
(multi-tenant, ver `docs/ANALISIS.md` §"Multi-tenencia"); un token de la
organización A nunca puede leer ni escribir datos de la organización B.

## Ejemplo con cURL

```bash
curl -X POST http://tu-dominio/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_TOKEN" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"buscar_catalogo","arguments":{"q":"contactor"}}}'
```

## Conectar un agente externo (Claude, opencode, etc.) a este MCP

Cualquier cliente MCP que soporte transporte HTTP puede apuntar a
`https://tu-dominio/mcp` con el header `Authorization: Bearer <token>`. No
hace falta configuración adicional en el servidor: basta con generar un
token con los permisos necesarios desde **Administración → Tokens de API**.

### Ejemplo: opencode (`~/.config/opencode/opencode.json`)

```json
"crux-ai": {
  "type": "remote",
  "url": "http://127.0.0.1:8090/mcp",
  "enabled": true,
  "headers": { "Authorization": "Bearer crux_..." }
}
```

Si el cliente exige transporte SSE en vez de JSON directo, usar el bridge
`mcp-remote` como servidor local:

```json
"crux-ai": {
  "type": "local",
  "command": ["npx", "mcp-remote", "http://127.0.0.1:8090/mcp",
              "--header", "Authorization: Bearer crux_..."],
  "enabled": true
}
```

## Notas de diseño

- Transporte "Streamable HTTP" simplificado: solo `POST` con respuesta JSON
  directa (sin Server-Sent Events). Suficiente para herramientas
  request/response como las de este proyecto; no hay mensajes que el
  servidor necesite iniciar de forma proactiva hacia el cliente.
- Los errores de protocolo van en el cuerpo JSON-RPC (`error`), no en el
  código de estado HTTP (siempre 200), tal como exige la especificación
  JSON-RPC 2.0.
