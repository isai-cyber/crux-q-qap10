<?php

declare(strict_types=1);

final class DisenosController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare(
            'SELECT d.*, a.nombre AS activo_nombre FROM disenos_tecnicos d
             LEFT JOIN activos a ON a.id = d.activo_id
             WHERE d.organizacion_id = :org
             ORDER BY d.created_at DESC'
        );
        $stmt->execute(['org' => $org]);
        $disenos = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/disenos/index.php';
    }

    public function create(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare('SELECT id, nombre FROM activos WHERE organizacion_id = :org ORDER BY nombre');
        $stmt->execute(['org' => $org]);
        $activos = $stmt->fetchAll();
        $productos = Database::connection()->query(
            'SELECT id, numero_parte, descripcion FROM productos_catalogo ORDER BY numero_parte'
        )->fetchAll();
        require dirname(__DIR__, 2) . '/views/modulos/disenos/form.php';
    }

    public function store(): void
    {
        Auth::requireLogin();
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            die('Token de seguridad inválido.');
        }

        $tipo = $_POST['tipo'] ?? '';
        $descripcion = trim($_POST['descripcion_solicitud'] ?? '');
        if (!in_array($tipo, ['diagrama', 'documento'], true) || $descripcion === '') {
            $_SESSION['form_errors'] = ['Selecciona un tipo válido y describe lo que necesitas.'];
            header('Location: ' . base_url('/disenos/nuevo'));
            exit;
        }

        $usuario = Auth::user();
        $org = $usuario['organizacion_id'];

        $activoId = null;
        if (!empty($_POST['activo_id'])) {
            $activoId = ActivoModel::find($org, (int) $_POST['activo_id']) ? (int) $_POST['activo_id'] : null;
        }
        $productoIds = array_filter(array_map('intval', (array) ($_POST['producto_ids'] ?? [])));

        $ai = new AIService();

        if ($tipo === 'diagrama') {
            $productos = [];
            if ($productoIds) {
                $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
                $stmt = Database::connection()->prepare(
                    "SELECT numero_parte, descripcion FROM productos_catalogo WHERE id IN ({$placeholders})"
                );
                $stmt->execute(array_values($productoIds));
                $productos = $stmt->fetchAll();
            }
            $contenido = $this->sanitizarSvg($ai->generateDiagram($descripcion, $productos));
            $formato = 'svg';
        } else {
            $contexto = [];
            if ($activoId) {
                $activo = ActivoModel::find($org, $activoId);
                if ($activo) {
                    $contexto['Activo'] = $activo['nombre'];
                    $contexto['Planta'] = $activo['planta_nombre'];
                }
            }
            $contenido = $ai->generateTechDoc($descripcion, $contexto);
            $formato = 'markdown';
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO disenos_tecnicos (organizacion_id, tipo, titulo, descripcion_solicitud, contenido, formato, activo_id, usuario_id)
             VALUES (:org, :tipo, :titulo, :descripcion, :contenido, :formato, :activo_id, :usuario_id)'
        );
        $stmt->execute([
            'org' => $org,
            'tipo' => $tipo,
            'titulo' => mb_strimwidth($descripcion, 0, 100, '…'),
            'descripcion' => $descripcion,
            'contenido' => $contenido,
            'formato' => $formato,
            'activo_id' => $activoId,
            'usuario_id' => $usuario['id'],
        ]);

        $id = (int) Database::connection()->lastInsertId();
        header('Location: ' . base_url('/disenos/' . $id));
        exit;
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $stmt = Database::connection()->prepare(
            'SELECT d.*, a.nombre AS activo_nombre FROM disenos_tecnicos d
             LEFT JOIN activos a ON a.id = d.activo_id
             WHERE d.id = :id AND d.organizacion_id = :org'
        );
        $stmt->execute(['id' => $id, 'org' => $org]);
        $diseno = $stmt->fetch();
        if (!$diseno) {
            http_response_code(404);
            require dirname(__DIR__, 2) . '/views/errors/404.php';
            return;
        }
        require dirname(__DIR__, 2) . '/views/modulos/disenos/show.php';
    }

    /**
     * Defensa en profundidad: el SVG lo produce un LLM a partir de texto
     * libre del usuario. Se muestra luego como <img> con data URI (los
     * navegadores no ejecutan <script> dentro de SVG en contexto <img>),
     * pero igual se elimina cualquier vector de script por si se reutiliza
     * el contenido en otro contexto (descarga, editor, etc.).
     */
    private function sanitizarSvg(string $svg): string
    {
        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $svg) ?? $svg;
        $svg = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $svg) ?? $svg;
        $svg = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $svg) ?? $svg;
        return $svg;
    }
}
