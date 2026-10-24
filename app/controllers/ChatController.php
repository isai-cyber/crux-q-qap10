<?php

declare(strict_types=1);

/**
 * ORION: asistente de chat interno del CMMS (sustituye a WhatsApp como
 * canal conversacional — ver docs/ANALISIS.md §6.2).
 *
 * Intenciones soportadas:
 *  1) Reporte de falla (por defecto): triage IA → crea ticket → sugiere runbook.
 *  2) Gestión de usuarios ("crea un técnico...", solo rol admin): da de alta
 *     un usuario con rol técnico/supervisor/admin sin salir del chat.
 *  3) Gestión de conexiones MCP externas ("conectar mcp: ...", solo rol
 *     admin): ORION actúa como cliente MCP (ver McpClientService) y consume
 *     herramientas de otros servidores MCP del mismo nicho.
 *  4) Llamada a una herramienta de una conexión MCP ya conectada, si el
 *     mensaje la menciona por nombre.
 * No hay un router de intenciones complejo: se detecta por palabras clave,
 * documentado como decisión de alcance para no construir NLU genérico.
 */
final class ChatController
{
    public const NOMBRE_ASISTENTE = 'ORION';

    public function index(): void
    {
        Auth::requireLogin();
        $usuario = Auth::user();

        $stmt = Database::connection()->prepare(
            'SELECT c.*, t.folio FROM chat_mensajes c
             LEFT JOIN tickets t ON t.id = c.ticket_id
             WHERE c.usuario_id = :usuario_id AND c.organizacion_id = :org
             ORDER BY c.created_at ASC
             LIMIT 100'
        );
        $stmt->execute(['usuario_id' => $usuario['id'], 'org' => $usuario['organizacion_id']]);
        $historial = $stmt->fetchAll();

        require dirname(__DIR__, 2) . '/views/modulos/chat/index.php';
    }

    public function enviar(): void
    {
        Auth::requireLogin();
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Token de seguridad inválido.']);
            return;
        }

        $mensaje = trim($_POST['mensaje'] ?? '');
        if ($mensaje === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Escribe una descripción de la falla.']);
            return;
        }

        $usuario = Auth::user();
        $org = (int) $usuario['organizacion_id'];

        $activoId = null;
        if (!empty($_POST['activo_id'])) {
            $activoId = ActivoModel::find($org, (int) $_POST['activo_id']) ? (int) $_POST['activo_id'] : null;
        }

        $respuesta = $this->procesar($org, $usuario, $mensaje, $activoId);

        header('Content-Type: application/json');
        echo json_encode($respuesta);
    }

    /**
     * Núcleo conversacional de ORION, compartido por el chat web (sesión +
     * CSRF) y la API de la app móvil (token Bearer, ver AuthApiController).
     * Guarda ambos mensajes en el historial y devuelve el envelope
     * {respuesta, ticket} que consumen ambos clientes.
     */
    public function procesar(int $org, array $usuario, string $mensaje, ?int $activoId = null): array
    {
        $this->guardarMensaje($org, (int) $usuario['id'], 'usuario', $mensaje, null);

        if ($this->pareceGestionMcp($mensaje)) {
            $respuesta = $this->manejarGestionMcp($org, $usuario, $mensaje);
            $this->guardarMensaje($org, (int) $usuario['id'], 'asistente', $respuesta, null);
            return ['respuesta' => $respuesta, 'ticket' => null];
        }

        $herramientaExterna = $this->detectarHerramientaExterna($org, $mensaje);
        if ($herramientaExterna !== null) {
            $respuesta = $this->manejarLlamadaHerramientaExterna($org, $mensaje, $herramientaExterna);
            $this->guardarMensaje($org, (int) $usuario['id'], 'asistente', $respuesta, null);
            return ['respuesta' => $respuesta, 'ticket' => null];
        }

        if ($this->pareceGestionDeUsuarios($mensaje)) {
            $respuesta = $this->manejarGestionUsuarios($org, $usuario, $mensaje);
            $this->guardarMensaje($org, (int) $usuario['id'], 'asistente', $respuesta, null);
            return ['respuesta' => $respuesta, 'ticket' => null];
        }

        if ($this->pareceConsultaDeManual($mensaje)) {
            $resultado = (new AIService($org))->responderConManuales($mensaje);
            $this->guardarMensaje($org, (int) $usuario['id'], 'asistente', $resultado['respuesta'], null);
            return ['respuesta' => $resultado['respuesta'], 'ticket' => null];
        }

        return $this->manejarReporteFalla($org, $usuario, $mensaje, $activoId);
    }

    private function manejarReporteFalla(int $org, array $usuario, string $mensaje, ?int $activoId = null): array
    {
        $triage = (new AIService($org))->triageTicket($mensaje);

        $ticket = TicketModel::create($org, [
            'activo_id' => $activoId,
            'reportado_por_id' => $usuario['id'],
            'canal_origen' => 'chat',
            'descripcion' => $mensaje,
            'categoria_ia' => $triage['categoria'] ?? null,
            'prioridad_ia' => $triage['prioridad'] ?? null,
        ]);

        $runbook = RunbookModel::sugerirPara($org, $mensaje, $ticket['activo_id'] ?? null);

        $respuesta = $this->componerRespuestaTicket($ticket, $triage, $runbook);
        $this->guardarMensaje($org, (int) $usuario['id'], 'asistente', $respuesta, (int) $ticket['id']);

        return [
            'respuesta' => $respuesta,
            'ticket' => ['id' => $ticket['id'], 'folio' => $ticket['folio']],
        ];
    }

    /**
     * Preguntas sobre los manuales (RAG), distintas de un reporte de falla:
     * empiezan con un interrogativo o mencionan "manual"/los productos
     * documentados (SINUMERIK, STEP7, PROFINET, SIMATIC Automation Tool).
     */
    private function pareceConsultaDeManual(string $mensaje): bool
    {
        $texto = mb_strtolower($mensaje);
        $esPregunta = (bool) preg_match('/^(qué|que|cómo|como|cuál|cual|dónde|donde|para qué|para que)\b/u', $texto)
            || str_ends_with(trim($texto), '?');
        $mencionaManual = (bool) preg_match('/\b(manual|sinumerik|step\s*7|profinet|simatic|automation tool|operate)\b/u', $texto);
        return $esPregunta || $mencionaManual;
    }

    private function pareceGestionDeUsuarios(string $mensaje): bool
    {
        $texto = mb_strtolower($mensaje);
        $tieneAccion = (bool) preg_match('/\b(crea|crear|agregar|añad[ei]r?|dar de alta|alta de|nuevo|nueva)\b/u', $texto);
        $tieneSujeto = (bool) preg_match('/\b(usuario|tecnico|técnico|supervisor|administrador|admin|empleado|rol)\b/u', $texto);
        return $tieneAccion && $tieneSujeto;
    }

    /**
     * Da de alta un usuario a partir de un comando de chat. Formato esperado
     * (documentado en la respuesta cuando no se puede interpretar):
     * "crear usuario: Nombre Apellido, email@empresa.com, tecnico"
     */
    private function manejarGestionUsuarios(int $org, array $usuario, string $mensaje): string
    {
        if ($usuario['rol'] !== 'admin') {
            return 'Solo un administrador puede crear usuarios desde ' . self::NOMBRE_ASISTENTE . '. '
                . 'Pídele a un admin de tu organización que lo haga, o hazlo desde Administración → Usuarios.';
        }

        $datos = $this->extraerDatosUsuario($mensaje);
        if ($datos === null) {
            return 'No pude entender los datos del nuevo usuario. Usa este formato: '
                . '"crear usuario: Nombre Apellido, email@empresa.com, tecnico[, contraseña]" '
                . '(rol: admin, supervisor o tecnico).';
        }

        $controller = new UsuariosController();
        $errors = $controller->validar($org, $datos);
        if ($errors) {
            return 'No se pudo crear el usuario: ' . implode(' ', $errors);
        }

        UsuarioModel::create($org, $datos);
        return "Listo, creé el usuario \"{$datos['nombre']}\" ({$datos['email']}) con rol \"{$datos['rol']}\". "
            . 'Ya puede iniciar sesión con la contraseña indicada.';
    }

    /**
     * Extracción de campos desde texto libre. Con IA configurada, delega en
     * el modelo (más tolerante a redacciones distintas); sin ella, exige el
     * formato estructurado documentado en manejarGestionUsuarios().
     */
    private function extraerDatosUsuario(string $mensaje): ?array
    {
        $ai = new AIService();
        if ($ai->isConfigured()) {
            $datos = $ai->extraerDatosUsuario($mensaje);
            if ($datos !== null) {
                return $datos;
            }
        }

        if (!preg_match('/:(.+)$/u', $mensaje, $m)) {
            return null;
        }
        $partes = array_map('trim', explode(',', $m[1]));
        if (count($partes) < 3) {
            return null;
        }

        [$nombre, $email, $rolTexto] = $partes;
        $password = $partes[3] ?? bin2hex(random_bytes(4));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $rol = match (true) {
            str_contains(mb_strtolower($rolTexto), 'admin') => 'admin',
            str_contains(mb_strtolower($rolTexto), 'supervisor') => 'supervisor',
            default => 'tecnico',
        };

        return ['nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'password' => $password];
    }

    /**
     * Gestión de conexiones MCP externas (agregar/listar/quitar) desde el
     * chat — ORION actúa como cliente MCP, no solo como servidor (ver
     * McpController para el lado servidor, y McpClientService para el HTTP).
     */
    private function pareceGestionMcp(string $mensaje): bool
    {
        $texto = mb_strtolower($mensaje);
        return (bool) preg_match('/\bmcp\b/u', $texto)
            && (bool) preg_match('/\b(conecta|conectar|agregar|añad[ei]r?|dar de alta|desconectar|eliminar|quitar|borrar|listar|ver|mostrar)\b/u', $texto);
    }

    private function manejarGestionMcp(int $org, array $usuario, string $mensaje): string
    {
        if ($usuario['rol'] !== 'admin') {
            return 'Solo un administrador puede gestionar conexiones MCP desde ' . self::NOMBRE_ASISTENTE . '.';
        }

        $texto = mb_strtolower($mensaje);

        if (preg_match('/\b(listar|ver|mostrar)\b/u', $texto)) {
            return $this->listarConexionesMcp($org);
        }

        if (preg_match('/\b(desconectar|eliminar|quitar|borrar)\b/u', $texto)) {
            return $this->desconectarMcp($org, $mensaje);
        }

        return $this->conectarMcp($org, $usuario, $mensaje);
    }

    /**
     * "conectar mcp: nombre, https://servidor.com/mcp[, token]" — prueba la
     * conexión (tools/list) antes de guardarla, para no dejar conexiones
     * rotas en la base.
     */
    private function conectarMcp(int $org, array $usuario, string $mensaje): string
    {
        $datos = $this->extraerDatosConexionMcp($mensaje);
        if ($datos === null) {
            return 'No pude entender los datos de la conexión. Usa este formato: '
                . '"conectar mcp: nombre, https://servidor.com/mcp[, token]".';
        }
        [$nombre, $url, $token] = $datos;

        $descubierto = McpClientService::descubrirHerramientas($url, $token);
        if (!$descubierto['success']) {
            return "No pude conectar con \"{$nombre}\": {$descubierto['error']}";
        }

        McpConexionModel::crear($org, $nombre, $url, $token, $descubierto['herramientas'], (int) $usuario['id']);

        $nombresHerramientas = array_map(fn ($h) => $h['name'] ?? '?', $descubierto['herramientas']);
        $listado = $nombresHerramientas ? implode(', ', $nombresHerramientas) : 'ninguna';

        return "Conectado a \"{$nombre}\". Encontré " . count($descubierto['herramientas'])
            . " herramienta(s): {$listado}. Ya puedes pedírmelas por su nombre en la conversación.";
    }

    private function extraerDatosConexionMcp(string $mensaje): ?array
    {
        if (!preg_match('/:(.+)$/u', $mensaje, $m)) {
            return null;
        }
        $partes = array_map('trim', explode(',', $m[1]));
        if (count($partes) < 2) {
            return null;
        }

        $nombre = $partes[0];
        $url = $partes[1];
        $token = $partes[2] ?? '';

        if ($nombre === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return [$nombre, $url, $token !== '' ? $token : null];
    }

    private function listarConexionesMcp(int $org): string
    {
        $conexiones = McpConexionModel::all($org);
        if (!$conexiones) {
            return 'No tienes ninguna conexión MCP configurada. Usa "conectar mcp: nombre, url[, token]" para agregar una.';
        }

        $lineas = [];
        foreach ($conexiones as $c) {
            $herramientas = json_decode($c['herramientas_cache'] ?? '[]', true) ?: [];
            $nombresHerramientas = implode(', ', array_map(fn ($h) => $h['name'] ?? '?', $herramientas)) ?: 'ninguna';
            $lineas[] = "• \"{$c['nombre']}\" ({$c['url']}) — herramientas: {$nombresHerramientas}";
        }

        return "Conexiones MCP activas:\n" . implode("\n", $lineas);
    }

    private function desconectarMcp(int $org, string $mensaje): string
    {
        if (preg_match('/:(.+)$/u', $mensaje, $m)) {
            $nombre = trim($m[1]);
        } else {
            $partes = preg_split('/\s+/', trim($mensaje)) ?: [];
            $nombre = trim((string) end($partes), "\"'.");
        }

        if ($nombre === '' || !McpConexionModel::existe($org, $nombre)) {
            return "No encontré ninguna conexión MCP llamada \"{$nombre}\".";
        }

        McpConexionModel::delete($org, $nombre);
        return "Listo, eliminé la conexión MCP \"{$nombre}\".";
    }

    /**
     * Busca si el mensaje menciona por nombre alguna herramienta de una
     * conexión MCP ya conectada, para invocarla en vez de tratar el mensaje
     * como reporte de falla o pregunta de manual.
     */
    private function detectarHerramientaExterna(int $org, string $mensaje): ?array
    {
        $herramientas = McpConexionModel::herramientasDisponibles($org);
        if (!$herramientas) {
            return null;
        }

        $texto = mb_strtolower($mensaje);
        foreach ($herramientas as $herramienta) {
            $nombreHerramienta = mb_strtolower($herramienta['nombre']);
            if ($nombreHerramienta !== '' && str_contains($texto, $nombreHerramienta)) {
                return $herramienta;
            }
        }

        return null;
    }

    private function manejarLlamadaHerramientaExterna(int $org, string $mensaje, array $herramienta): string
    {
        $argumentos = (new AIService($org))->extraerArgumentosHerramienta($mensaje, $herramienta['inputSchema']);
        if ($argumentos === null) {
            $propiedades = array_keys($herramienta['inputSchema']['properties'] ?? []);
            $listaCampos = $propiedades ? implode(', ', $propiedades) : 'sin parámetros definidos';
            return "Para usar \"{$herramienta['nombre']}\" (de \"{$herramienta['conexion']}\") necesito más datos: {$listaCampos}.";
        }

        $resultado = McpClientService::llamarHerramienta($herramienta['url'], $herramienta['token'], $herramienta['nombre'], $argumentos);
        if (!$resultado['success']) {
            return "Error al llamar \"{$herramienta['nombre']}\": {$resultado['error']}";
        }

        return $resultado['texto'];
    }

    private function componerRespuestaTicket(array $ticket, array $triage, ?array $runbook): string
    {
        $partes = [];
        $partes[] = "He registrado el ticket {$ticket['folio']}, clasificado como prioridad "
            . "\"{$triage['prioridad']}\" (categoría: {$triage['categoria']}).";

        if ($runbook !== null) {
            $partes[] = 'Encontré un procedimiento' . ($runbook['validado'] ? ' ya validado' : ' sugerido')
                . " para un caso similar: \"{$runbook['titulo']}\" → {$runbook['solucion']}";
        } else {
            $partes[] = 'Aún no hay un procedimiento previo para un caso similar; se creó una orden de trabajo pendiente para que un técnico lo revise.';
        }

        return implode(' ', $partes);
    }

    private function guardarMensaje(int $org, int $usuarioId, string $rol, string $contenido, ?int $ticketId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO chat_mensajes (organizacion_id, usuario_id, rol, contenido, ticket_id)
             VALUES (:org, :usuario_id, :rol, :contenido, :ticket_id)'
        );
        $stmt->execute([
            'org' => $org,
            'usuario_id' => $usuarioId,
            'rol' => $rol,
            'contenido' => $contenido,
            'ticket_id' => $ticketId,
        ]);
    }
}
