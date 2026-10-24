<?php

declare(strict_types=1);

/**
 * API REST para la app móvil (Flutter/React Native) y clientes interactivos.
 * Reutiliza la autenticación Bearer de ApiAuth y los mismos modelos que la
 * web; el chat delega en ChatController::procesar() para que ORION se
 * comporte idéntico en ambos canales.
 */
final class AuthApiController
{
    /** Roles con permiso completo sobre los tokens que generan. */
    private const PERMISOS_TODOS = [
        'crear_ticket',
        'consultar_activos',
        'crear_activo',
        'consultar_catalogo',
        'consultar_alertas',
        'crear_usuario',
        'consultar_tickets',
        'actualizar_ticket',
        'usar_chat',
    ];

    /** Subconjunto operativo para técnicos de planta. */
    private const PERMISOS_TECNICO = [
        'consultar_tickets',
        'actualizar_ticket',
        'usar_chat',
        'consultar_activos',
        'consultar_catalogo',
        'consultar_alertas',
    ];

    public function login(): void
    {
        $this->ejecutar(fn () => $this->loginInterno());
    }

    private function loginInterno(): void
    {
        $input = $this->jsonBody();
        $usuario = trim((string) ($input['usuario'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $dispositivo = trim((string) ($input['nombre_dispositivo'] ?? '')) ?: 'dispositivo';

        if ($usuario === '' || $password === '') {
            $this->json(422, ['error' => 'usuario y password son obligatorios.']);
            return;
        }

        $candidatos = UsuarioModel::findActiveByUsername($usuario);
        $usuario = null;
        foreach ($candidatos as $candidato) {
            if (password_verify($password, $candidato['password_hash'])) {
                $usuario = $candidato;
                break;
            }
        }

        if ($usuario === null) {
            // Fuerza de trabajo constante ante intentos fallidos (no hay
            // rate limiter aún; ver docs/API.md §seguridad).
            usleep(random_int(50000, 150000));
            $this->json(401, ['error' => 'Credenciales incorrectas.']);
            return;
        }

        $org = (int) $usuario['organizacion_id'];
        $permisos = in_array($usuario['rol'], ['admin', 'supervisor'], true)
            ? self::PERMISOS_TODOS
            : self::PERMISOS_TECNICO;

        $db = Database::connection();

        // Un dispositivo = un token: el re-login revoca el anterior de ese
        // equipo para no acumular credenciales huérfanas.
        $revocar = $db->prepare(
            'UPDATE api_tokens SET activo = 0
             WHERE usuario_creador_id = :uid AND nombre = :nombre AND activo = 1'
        );
        $revocar->execute([
            'uid' => (int) $usuario['id'],
            'nombre' => 'App móvil — ' . $dispositivo,
        ]);

        $tokenPlano = 'crux_' . bin2hex(random_bytes(24));
        $insert = $db->prepare(
            'INSERT INTO api_tokens (organizacion_id, nombre, token_hash, permisos, usuario_creador_id, activo)
             VALUES (:org, :nombre, :token_hash, :permisos, :uid, 1)'
        );
        $insert->execute([
            'org' => $org,
            'nombre' => 'App móvil — ' . $dispositivo,
            'token_hash' => hash('sha256', $tokenPlano),
            'permisos' => json_encode($permisos),
            'uid' => (int) $usuario['id'],
        ]);

        $this->json(201, [
            'token' => $tokenPlano,
            'usuario' => [
                'id' => (int) $usuario['id'],
                'nombre' => $usuario['nombre'],
                'usuario' => $usuario['usuario'],
                'email' => $usuario['email'],
                'rol' => $usuario['rol'],
            ],
            'organizacion_id' => $org,
            'permisos' => $permisos,
        ]);
    }

    /**
     * Ejecuta una operación capturando los rechazos de ApiAuth para
     * devolverlos como JSON con su status HTTP (401/403) en vez de dejar
     * propagar la ApiAuthException.
     */
    private function ejecutar(callable $operacion): void
    {
        try {
            $operacion();
        } catch (ApiAuthException $e) {
            $this->json($e->status, ['error' => $e->getMessage()]);
        }
    }

    public function me(): void
    {
        $this->ejecutar(fn () => $this->meInterno());
    }

    private function meInterno(): void
    {
        $token = ApiAuth::authenticate();
        $org = (int) $token['organizacion_id'];

        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, usuario, email, rol, organizacion_id FROM usuarios
             WHERE id = :id AND organizacion_id = :org LIMIT 1'
        );
        $stmt->execute(['id' => $token['usuario_creador_id'], 'org' => $org]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $this->json(404, ['error' => 'El token no está asociado a un usuario vigente.']);
            return;
        }

        $this->json(200, [
            'usuario' => $usuario,
            'permisos' => json_decode($token['permisos'], true) ?: [],
        ]);
    }

    public function tickets(): void
    {
        $this->ejecutar(fn () => $this->ticketsInterno());
    }

    private function ticketsInterno(): void
    {
        $token = ApiAuth::authenticate('consultar_tickets');
        $org = (int) $token['organizacion_id'];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $estado = trim((string) ($_GET['estado'] ?? ''));

        $this->json(200, TicketModel::paginate($org, $page, 20, $estado !== '' ? $estado : null));
    }

    public function ticket(string $id): void
    {
        $this->ejecutar(fn () => $this->ticketInterno($id));
    }

    private function ticketInterno(string $id): void
    {
        $token = ApiAuth::authenticate('consultar_tickets');
        $ticket = TicketModel::find((int) $token['organizacion_id'], (int) $id);

        if (!$ticket) {
            $this->json(404, ['error' => 'Ticket no encontrado.']);
            return;
        }

        $this->json(200, $ticket);
    }

    public function cambiarEstado(string $id): void
    {
        $this->ejecutar(fn () => $this->cambiarEstadoInterno($id));
    }

    private function cambiarEstadoInterno(string $id): void
    {
        $token = ApiAuth::authenticate('actualizar_ticket');
        $org = (int) $token['organizacion_id'];

        $input = $this->jsonBody();
        $estadosValidos = ['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'];
        $estado = (string) ($input['estado'] ?? '');

        if (!in_array($estado, $estadosValidos, true)) {
            $this->json(422, ['error' => 'Estado inválido. Valores: ' . implode(', ', $estadosValidos)]);
            return;
        }

        $ticket = TicketModel::find($org, (int) $id);
        if (!$ticket) {
            $this->json(404, ['error' => 'Ticket no encontrado.']);
            return;
        }

        TicketModel::updateStatus(
            $org,
            (int) $id,
            $estado,
            isset($input['prioridad_final']) ? ($input['prioridad_final'] ?: null) : null,
            isset($input['categoria_final']) ? ($input['categoria_final'] ?: null) : null
        );

        $this->json(200, TicketModel::find($org, (int) $id));
    }

    public function chat(): void
    {
        $this->ejecutar(fn () => $this->chatInterno());
    }

    private function chatInterno(): void
    {
        $token = ApiAuth::authenticate('usar_chat');
        $org = (int) $token['organizacion_id'];

        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, usuario, email, rol FROM usuarios
             WHERE id = :id AND organizacion_id = :org LIMIT 1'
        );
        $stmt->execute(['id' => $token['usuario_creador_id'], 'org' => $org]);
        $usuario = $stmt->fetch();
        if (!$usuario) {
            $this->json(404, ['error' => 'El token no está asociado a un usuario vigente.']);
            return;
        }

        $input = $this->jsonBody();
        $mensaje = trim((string) ($input['mensaje'] ?? ''));
        if ($mensaje === '') {
            $this->json(422, ['error' => 'El campo mensaje es obligatorio.']);
            return;
        }
        if (mb_strlen($mensaje) > 2000) {
            $this->json(422, ['error' => 'El mensaje no puede exceder 2000 caracteres.']);
            return;
        }

        $activoId = null;
        if (!empty($input['activo_id']) && ActivoModel::find($org, (int) $input['activo_id'])) {
            $activoId = (int) $input['activo_id'];
        }

        $respuesta = (new ChatController())->procesar($org, $usuario, $mensaje, $activoId);
        $this->json(200, $respuesta);
    }

    public function activos(): void
    {
        $this->ejecutar(fn () => $this->activosInterno());
    }

    private function activosInterno(): void
    {
        $token = ApiAuth::authenticate('consultar_activos');
        $org = (int) $token['organizacion_id'];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $q = trim((string) ($_GET['q'] ?? ''));

        $this->json(200, ActivoModel::paginate($org, $page, 20, $q !== '' ? $q : null));
    }

    public function dashboard(): void
    {
        $this->ejecutar(fn () => $this->dashboardInterno());
    }

    private function dashboardInterno(): void
    {
        $token = ApiAuth::authenticate('consultar_tickets');
        $org = (int) $token['organizacion_id'];

        $datos = (new DashboardController())->datos($org);

        $this->json(200, [
            'kpis' => $datos['kpis'],
            'tickets_recientes' => $datos['tickets_recientes'],
            'tickets_por_estado' => $datos['tickets_por_estado'],
            'tickets_por_dia' => $datos['tickets_por_dia'],
            'tickets_por_prioridad' => $datos['tickets_por_prioridad'],
            'tasa_cierre' => $datos['tasa_cierre'],
            'total_tickets' => $datos['total_tickets'],
        ]);
    }

    public function usoIA(): void
    {
        $this->ejecutar(fn () => $this->usoIAInterno());
    }

    private function usoIAInterno(): void
    {
        $token = ApiAuth::authenticate();
        $org = (int) $token['organizacion_id'];

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

        $this->json(200, [
            'llamadas' => (int) $totales['llamadas'],
            'prompt_tokens' => (int) $totales['prompt_tokens'],
            'completion_tokens' => (int) $totales['completion_tokens'],
            'total_tokens' => (int) $totales['prompt_tokens'] + (int) $totales['completion_tokens'],
            'latencia_media_ms' => (int) round((float) $totales['latencia_media_ms']),
            'por_endpoint' => $porEndpointStmt->fetchAll(),
        ]);
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        return is_array($data) ? $data : [];
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
