<?php

declare(strict_types=1);

/**
 * Whitelabel: permite al admin de cada organización subir su propio logo,
 * que reemplaza la marca de Crux A.I en el sidebar/header de la app.
 */
final class OrganizacionController
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MIME_EXT = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function editar(): void
    {
        Auth::requireRole('admin');
        $orgId = (int) Auth::user()['organizacion_id'];
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM organizaciones WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orgId]);
        $org = $stmt->fetch();

        $totalUsuarios = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE organizacion_id = ' . $orgId)->fetchColumn();
        $conexionesMcp = McpConexionModel::all($orgId);

        $error = $_SESSION['org_error'] ?? null;
        $success = $_SESSION['org_success'] ?? null;
        $onboarding = !empty($_GET['onboarding']);
        unset($_SESSION['org_error'], $_SESSION['org_success']);

        require dirname(__DIR__, 2) . '/views/modulos/organizacion/index.php';
    }

    public function actualizarIA(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['org_error'] = 'Sesión inválida.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        $orgId = (int) Auth::user()['organizacion_id'];
        $provider = trim($_POST['ai_provider'] ?? '');
        $apiKey = trim($_POST['ai_api_key'] ?? '');
        $model = trim($_POST['ai_model'] ?? '');

        if (!in_array($provider, ['openai', 'anthropic', 'deepseek'], true)) {
            $_SESSION['org_error'] = 'Selecciona un proveedor de IA válido.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        // Campo en blanco = no tocar la clave ya guardada (el placeholder del
        // formulario lo promete así); solo se borra explícitamente con
        // "quitar clave" para no perderla por accidente al editar el modelo.
        if ($apiKey === '' && !empty($_POST['quitar_clave'])) {
            $apiKey = null;
        } elseif ($apiKey === '') {
            $stmt = Database::connection()->prepare('SELECT ai_api_key FROM organizaciones WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $orgId]);
            $apiKey = $stmt->fetchColumn() ?: null;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE organizaciones SET ai_provider = :provider, ai_api_key = :api_key, ai_model = :model WHERE id = :id'
        );
        $stmt->execute([
            'provider' => $provider,
            'api_key' => $apiKey,
            'model' => $model !== '' ? $model : null,
            'id' => $orgId,
        ]);

        $_SESSION['org_success'] = !empty($apiKey)
            ? 'Clave de IA guardada. A partir de ahora ORION y el triage usan tu propio proveedor.'
            : 'Se quitó la clave configurada; se usará el respaldo del sistema (o el heurístico si no hay ninguno).';
        header('Location: ' . base_url('/admin/organizacion'));
        exit;
    }

    public function eliminarConexionMcp(string $nombre): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['org_error'] = 'Sesión inválida.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        $orgId = (int) Auth::user()['organizacion_id'];
        McpConexionModel::delete($orgId, $nombre);

        $_SESSION['org_success'] = 'Conexión MCP "' . $nombre . '" eliminada.';
        header('Location: ' . base_url('/admin/organizacion'));
        exit;
    }

    public function actualizarLogo(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['org_error'] = 'Sesión inválida.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        $orgId = (int) Auth::user()['organizacion_id'];
        $archivo = $_FILES['logo'] ?? null;

        if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['org_error'] = 'Selecciona una imagen.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['org_error'] = 'Error al subir el archivo.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }
        if ($archivo['size'] > self::MAX_BYTES) {
            $_SESSION['org_error'] = 'La imagen no puede superar 2 MB.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        $mime = (string) mime_content_type($archivo['tmp_name']);
        if (!isset(self::MIME_EXT[$mime])) {
            $_SESSION['org_error'] = 'Formato no soportado. Usa PNG, JPG, WEBP o SVG.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        $destino = dirname(__DIR__, 2) . '/public/uploads/logos';
        if (!is_dir($destino)) {
            mkdir($destino, 0755, true);
        }

        foreach (glob($destino . '/org_' . $orgId . '_*') ?: [] as $anterior) {
            @unlink($anterior);
        }

        $nombreArchivo = 'org_' . $orgId . '_' . time() . '.' . self::MIME_EXT[$mime];
        move_uploaded_file($archivo['tmp_name'], $destino . '/' . $nombreArchivo);

        $stmt = Database::connection()->prepare('UPDATE organizaciones SET logo_path = :logo WHERE id = :id');
        $stmt->execute(['logo' => $nombreArchivo, 'id' => $orgId]);

        $_SESSION['org_success'] = 'Logo actualizado correctamente.';
        header('Location: ' . base_url('/admin/organizacion'));
        exit;
    }

    public function eliminarLogo(): void
    {
        Auth::requireRole('admin');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['org_error'] = 'Sesión inválida.';
            header('Location: ' . base_url('/admin/organizacion'));
            exit;
        }

        $orgId = (int) Auth::user()['organizacion_id'];
        $destino = dirname(__DIR__, 2) . '/public/uploads/logos';
        foreach (glob($destino . '/org_' . $orgId . '_*') ?: [] as $anterior) {
            @unlink($anterior);
        }

        $stmt = Database::connection()->prepare('UPDATE organizaciones SET logo_path = NULL WHERE id = :id');
        $stmt->execute(['id' => $orgId]);

        $_SESSION['org_success'] = 'Logo eliminado; se usará el logo predeterminado.';
        header('Location: ' . base_url('/admin/organizacion'));
        exit;
    }
}
