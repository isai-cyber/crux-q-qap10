<?php

declare(strict_types=1);

/**
 * Servidor MCP (Model Context Protocol) en PHP puro sobre HTTP, sin
 * dependencias de Node/Python: MCP es solo JSON-RPC 2.0, así que se
 * implementa el subconjunto necesario (initialize, tools/list, tools/call)
 * directamente en el stack PHP/Apache obligatorio del proyecto.
 *
 * Expone como "tools" MCP las mismas operaciones que la API REST
 * (docs/API.md), para que Hermes Agent u otro cliente MCP (incluido un
 * asistente como Claude) puedan operar el CMMS. Autenticación por el mismo
 * token Bearer de api_tokens — ver docs/MCP.md.
 */
final class McpController
{
    private const HERRAMIENTAS = [
        'crear_ticket' => [
            'permiso' => 'crear_ticket',
            'description' => 'Crea un ticket de falla y lo clasifica automáticamente con IA (prioridad y categoría).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'descripcion' => ['type' => 'string', 'description' => 'Descripción de la falla reportada'],
                    'activo_nombre' => ['type' => 'string', 'description' => 'Nombre del activo relacionado (opcional)'],
                    'canal_origen' => ['type' => 'string', 'description' => 'Canal de origen: chat, whatsapp, slack, email, api'],
                ],
                'required' => ['descripcion'],
            ],
        ],
        'consultar_activo' => [
            'permiso' => 'consultar_activos',
            'description' => 'Consulta un activo por ID, incluyendo su historial de diagnósticos.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => 'ID del activo']],
                'required' => ['id'],
            ],
        ],
        'crear_activo' => [
            'permiso' => 'crear_activo',
            'description' => 'Da de alta un activo (equipo) en el CMDB.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'nombre' => ['type' => 'string'],
                    'planta_id' => ['type' => 'integer'],
                    'tipo' => ['type' => 'string'],
                    'ubicacion' => ['type' => 'string'],
                    'ip_address' => ['type' => 'string'],
                ],
                'required' => ['nombre', 'planta_id'],
            ],
        ],
        'crear_usuario' => [
            'permiso' => 'crear_usuario',
            'description' => 'Crea un usuario (técnico, supervisor o admin) dentro de la organización del token.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'nombre' => ['type' => 'string'],
                    'usuario' => ['type' => 'string', 'description' => 'Nombre de usuario para iniciar sesión (opcional; si se omite, se deriva del email)'],
                    'email' => ['type' => 'string'],
                    'password' => ['type' => 'string'],
                    'rol' => ['type' => 'string', 'enum' => ['admin', 'supervisor', 'tecnico']],
                ],
                'required' => ['nombre', 'email', 'password', 'rol'],
            ],
        ],
        'buscar_catalogo' => [
            'permiso' => 'consultar_catalogo',
            'description' => 'Busca productos en el catálogo Siemens por número de parte o descripción.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['q' => ['type' => 'string', 'description' => 'Texto de búsqueda']],
                'required' => ['q'],
            ],
        ],
        'alertas_pendientes' => [
            'permiso' => 'consultar_alertas',
            'description' => 'Lista tickets críticos sin atender y productos sin stock (para vigilancia proactiva).',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ],
        'listar_tickets' => [
            'permiso' => 'consultar_tickets',
            'description' => 'Lista tickets de la organización con filtro opcional por estado y paginación.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'estado' => ['type' => 'string', 'enum' => ['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'], 'description' => 'Filtro por estado (opcional)'],
                    'pagina' => ['type' => 'integer', 'description' => 'Número de página (default 1)'],
                ],
            ],
        ],
        'consultar_ticket' => [
            'permiso' => 'consultar_tickets',
            'description' => 'Consulta el detalle de un ticket por ID.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => 'ID del ticket']],
                'required' => ['id'],
            ],
        ],
        'cambiar_estado_ticket' => [
            'permiso' => 'actualizar_ticket',
            'description' => 'Cambia el estado de un ticket (abierto, en_progreso, esperando_repuesto, cerrado, cancelado).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'ID del ticket'],
                    'estado' => ['type' => 'string', 'enum' => ['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado']],
                ],
                'required' => ['id', 'estado'],
            ],
        ],
        'listar_activos' => [
            'permiso' => 'consultar_activos',
            'description' => 'Lista activos de la organización con búsqueda opcional por nombre.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'q' => ['type' => 'string', 'description' => 'Texto de búsqueda por nombre (opcional)'],
                    'pagina' => ['type' => 'integer', 'description' => 'Número de página (default 1)'],
                ],
            ],
        ],
        'resumen_dashboard' => [
            'permiso' => 'consultar_tickets',
            'description' => 'KPIs de la organización: tickets abiertos/críticos, activos fuera de servicio, sin stock, distribución por estado/día/prioridad y tasa de cierre.',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ],
        'uso_ia' => [
            'permiso' => 'consultar_tickets',
            'description' => 'Consumo de IA de la organización: llamadas, tokens de entrada/salida y latencia media, con desglose por endpoint.',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ],
    ];

    public function handle(): void
    {
        header('Content-Type: application/json');
        $body = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($body) || !isset($body['jsonrpc'], $body['method'])) {
            $this->responder(null, null, ['code' => -32600, 'message' => 'Solicitud JSON-RPC inválida.']);
            return;
        }

        $id = $body['id'] ?? null;
        $method = $body['method'];
        $params = $body['params'] ?? [];

        try {
            $resultado = match ($method) {
                'initialize' => $this->initialize(),
                'tools/list' => $this->listarHerramientas(),
                'tools/call' => $this->ejecutarHerramienta($params),
                default => throw new RuntimeException("Método no soportado: {$method}"),
            };
            $this->responder($id, $resultado, null);
        } catch (Throwable $e) {
            $this->responder($id, null, ['code' => -32000, 'message' => $e->getMessage()]);
        }
    }

    private function initialize(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'serverInfo' => ['name' => 'sirius-maintenance-hub-mcp', 'version' => '1.0.0'],
            'capabilities' => ['tools' => new stdClass()],
        ];
    }

    private function listarHerramientas(): array
    {
        $tools = [];
        foreach (self::HERRAMIENTAS as $nombre => $def) {
            $schema = $def['inputSchema'];
            if (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            }
            $tools[] = [
                'name' => $nombre,
                'description' => $def['description'],
                'inputSchema' => $schema,
            ];
        }
        return ['tools' => $tools];
    }

    private function ejecutarHerramienta(array $params): array
    {
        $nombre = $params['name'] ?? '';
        $argumentos = $params['arguments'] ?? [];

        if (!isset(self::HERRAMIENTAS[$nombre])) {
            throw new RuntimeException("Herramienta desconocida: {$nombre}");
        }

        // Autenticación envuelta: un fallo de ApiAuth se convierte en error
        // JSON-RPC -32000 con HTTP 200, como exige el protocolo (antes
        // ApiAuth terminaba la petición con 401/403 crudo rompiendo el
        // envelope JSON-RPC). Ver docs/MCP.md §transporte.
        try {
            $token = ApiAuth::authenticate(self::HERRAMIENTAS[$nombre]['permiso']);
        } catch (ApiAuthException $e) {
            throw new RuntimeException($e->getMessage());
        }
        $org = (int) $token['organizacion_id'];

        $resultado = match ($nombre) {
            'crear_ticket' => $this->herramientaCrearTicket($org, $argumentos),
            'consultar_activo' => $this->herramientaConsultarActivo($org, $argumentos),
            'crear_activo' => $this->herramientaCrearActivo($org, $argumentos),
            'crear_usuario' => $this->herramientaCrearUsuario($org, $argumentos),
            'buscar_catalogo' => $this->herramientaBuscarCatalogo($org, $argumentos),
            'alertas_pendientes' => $this->herramientaAlertasPendientes($org),
            'listar_tickets' => $this->herramientaListarTickets($org, $argumentos),
            'consultar_ticket' => $this->herramientaConsultarTicket($org, $argumentos),
            'cambiar_estado_ticket' => $this->herramientaCambiarEstadoTicket($org, $argumentos),
            'listar_activos' => $this->herramientaListarActivos($org, $argumentos),
            'resumen_dashboard' => $this->herramientaResumenDashboard($org),
            'uso_ia' => $this->herramientaUsoIA($org),
        };

        return [
            'content' => [['type' => 'text', 'text' => json_encode($resultado, JSON_UNESCAPED_UNICODE)]],
        ];
    }

    private function herramientaCrearTicket(int $org, array $args): array
    {
        $descripcion = trim($args['descripcion'] ?? '');
        if ($descripcion === '') {
            throw new RuntimeException('descripcion es obligatoria.');
        }

        $activoId = null;
        if (!empty($args['activo_nombre'])) {
            $stmt = Database::connection()->prepare('SELECT id FROM activos WHERE nombre = :nombre AND organizacion_id = :org LIMIT 1');
            $stmt->execute(['nombre' => $args['activo_nombre'], 'org' => $org]);
            $activoId = $stmt->fetchColumn() ?: null;
        }

        $triage = (new AIService($org))->triageTicket($descripcion);
        $canal = $args['canal_origen'] ?? 'api';
        $canalValido = in_array($canal, ['web', 'chat', 'whatsapp', 'slack', 'email', 'api'], true) ? $canal : 'api';
        $ticket = TicketModel::create($org, [
            'activo_id' => $activoId,
            'canal_origen' => $canalValido,
            'descripcion' => $descripcion,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
        ]);
        $runbook = RunbookModel::sugerirPara($org, $descripcion, $activoId);

        return ['ticket' => $ticket, 'triage' => $triage, 'runbook_sugerido' => $runbook];
    }

    private function herramientaConsultarActivo(int $org, array $args): array
    {
        $activo = ActivoModel::find($org, (int) ($args['id'] ?? 0));
        if (!$activo) {
            throw new RuntimeException('Activo no encontrado.');
        }
        $activo['diagnosticos'] = ActivoModel::diagnosticos((int) $activo['id']);
        return $activo;
    }

    private function herramientaCrearActivo(int $org, array $args): array
    {
        if (trim($args['nombre'] ?? '') === '' || empty($args['planta_id'])) {
            throw new RuntimeException('nombre y planta_id son obligatorios.');
        }

        // Validación multi-tenant: la planta debe pertenecer a la organización
        // del token (misma regla que ApiController::crearActivo).
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM plantas WHERE id = :id AND organizacion_id = :org'
        );
        $stmt->execute(['id' => $args['planta_id'], 'org' => $org]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException('planta_id no pertenece a tu organización.');
        }

        $id = ActivoModel::create($org, $args);
        return ActivoModel::find($org, $id);
    }

    private function herramientaCrearUsuario(int $org, array $args): array
    {
        $controller = new UsuariosController();
        $errors = $controller->validar($org, $args);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }
        $id = UsuarioModel::create($org, $args);
        $usuario = UsuarioModel::find($org, $id);
        unset($usuario['password_hash']);
        return $usuario;
    }

    private function herramientaBuscarCatalogo(int $org, array $args): array
    {
        $resultado = ProductoModel::paginate($org, 1, 20, trim($args['q'] ?? '') ?: null);
        return $resultado['items'];
    }

    private function herramientaAlertasPendientes(int $org): array
    {
        $db = Database::connection();
        $ticketsStmt = $db->prepare(
            "SELECT id, folio, descripcion, prioridad_ia, fecha_apertura FROM tickets
             WHERE organizacion_id = :org AND estado = 'abierto'
                AND (prioridad_final = 'critica' OR (prioridad_final IS NULL AND prioridad_ia = 'critica'))
             ORDER BY fecha_apertura ASC"
        );
        $ticketsStmt->execute(['org' => $org]);

        $stockStmt = $db->prepare(
            'SELECT p.id, p.numero_parte, p.descripcion FROM productos_catalogo p
             LEFT JOIN inventario i ON i.producto_id = p.id AND i.organizacion_id = :org
             WHERE COALESCE(i.stock_actual, 0) = 0'
        );
        $stockStmt->execute(['org' => $org]);

        return [
            'tickets_criticos_sin_atender' => $ticketsStmt->fetchAll(),
            'productos_sin_stock' => $stockStmt->fetchAll(),
        ];
    }

    private function herramientaListarTickets(int $org, array $args): array
    {
        $pagina = max(1, (int) ($args['pagina'] ?? 1));
        $estado = trim((string) ($args['estado'] ?? ''));
        return TicketModel::paginate($org, $pagina, 20, $estado !== '' ? $estado : null);
    }

    private function herramientaConsultarTicket(int $org, array $args): array
    {
        $ticket = TicketModel::find($org, (int) ($args['id'] ?? 0));
        if (!$ticket) {
            throw new RuntimeException('Ticket no encontrado.');
        }
        return $ticket;
    }

    private function herramientaCambiarEstadoTicket(int $org, array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        $estado = (string) ($args['estado'] ?? '');
        $estadosValidos = ['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'];

        if (!in_array($estado, $estadosValidos, true)) {
            throw new RuntimeException('Estado inválido. Valores: ' . implode(', ', $estadosValidos));
        }

        $ticket = TicketModel::find($org, $id);
        if (!$ticket) {
            throw new RuntimeException('Ticket no encontrado.');
        }

        TicketModel::updateStatus($org, $id, $estado);
        return TicketModel::find($org, $id);
    }

    private function herramientaListarActivos(int $org, array $args): array
    {
        $pagina = max(1, (int) ($args['pagina'] ?? 1));
        $q = trim((string) ($args['q'] ?? ''));
        return ActivoModel::paginate($org, $pagina, 20, $q !== '' ? $q : null);
    }

    private function herramientaResumenDashboard(int $org): array
    {
        $datos = (new DashboardController())->datos($org);
        return [
            'kpis' => $datos['kpis'],
            'tickets_por_estado' => $datos['tickets_por_estado'],
            'tickets_por_dia' => $datos['tickets_por_dia'],
            'tickets_por_prioridad' => $datos['tickets_por_prioridad'],
            'tasa_cierre' => $datos['tasa_cierre'],
            'total_tickets' => $datos['total_tickets'],
        ];
    }

    private function herramientaUsoIA(int $org): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS llamadas,
                    COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                    COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
                    COALESCE(AVG(latencia_ms), 0) AS latencia_media_ms
             FROM logs_ia WHERE organizacion_id = :org'
        );
        $stmt->execute(['org' => $org]);
        $totales = $stmt->fetch();

        $porEndpointStmt = $db->prepare(
            'SELECT endpoint, COUNT(*) AS llamadas,
                    COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                    COALESCE(SUM(completion_tokens), 0) AS completion_tokens
             FROM logs_ia WHERE organizacion_id = :org
             GROUP BY endpoint ORDER BY llamadas DESC'
        );
        $porEndpointStmt->execute(['org' => $org]);

        return [
            'llamadas' => (int) $totales['llamadas'],
            'prompt_tokens' => (int) $totales['prompt_tokens'],
            'completion_tokens' => (int) $totales['completion_tokens'],
            'total_tokens' => (int) $totales['prompt_tokens'] + (int) $totales['completion_tokens'],
            'latencia_media_ms' => (int) round((float) $totales['latencia_media_ms']),
            'por_endpoint' => $porEndpointStmt->fetchAll(),
        ];
    }

    private function responder(mixed $id, mixed $result, ?array $error): void
    {
        $payload = ['jsonrpc' => '2.0', 'id' => $id];
        if ($error !== null) {
            http_response_code(200); // JSON-RPC reporta errores en el cuerpo, no en el status HTTP
            $payload['error'] = $error;
        } else {
            $payload['result'] = $result;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
