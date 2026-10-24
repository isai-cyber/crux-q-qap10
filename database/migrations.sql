-- Migración: Diseños técnicos con IA + ciclo de autoevolución de runbooks/triage
-- Aplicar sobre una base ya creada con schema.sql (versión anterior a esta fecha).
USE cmms_industrial;

-- Runbooks: contadores de éxito/fallo real y bandera de vigencia (se retira solo si falla mucho)
ALTER TABLE runbooks
    ADD COLUMN vigente TINYINT(1) NOT NULL DEFAULT 1 AFTER validado,
    ADD COLUMN veces_exitoso INT UNSIGNED NOT NULL DEFAULT 0 AFTER veces_aplicado,
    ADD COLUMN veces_fallido INT UNSIGNED NOT NULL DEFAULT 0 AFTER veces_exitoso;

-- Registro de cada vez que se aplica un runbook a un ticket, con su resultado real
CREATE TABLE IF NOT EXISTS runbook_aplicaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    runbook_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    resultado ENUM('exitoso', 'fallido') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_runbook_aplicacion_runbook FOREIGN KEY (runbook_id) REFERENCES runbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_runbook_aplicacion_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Órdenes de trabajo: qué runbook se usó y si la solución funcionó (retroalimentación)
ALTER TABLE ordenes_trabajo
    ADD COLUMN runbook_id INT UNSIGNED NULL AFTER tecnico_id,
    ADD COLUMN solucion_exitosa TINYINT(1) NULL AFTER solucion_aplicada,
    ADD CONSTRAINT fk_orden_runbook FOREIGN KEY (runbook_id) REFERENCES runbooks(id) ON DELETE SET NULL;

-- Diseños técnicos generados por IA (diagramas SVG y documentos), asociables a activo/ticket/runbook
CREATE TABLE IF NOT EXISTS disenos_tecnicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('diagrama', 'documento') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion_solicitud TEXT NOT NULL,
    contenido LONGTEXT NOT NULL,
    formato ENUM('svg', 'markdown') NOT NULL,
    activo_id INT UNSIGNED NULL,
    ticket_id INT UNSIGNED NULL,
    runbook_id INT UNSIGNED NULL,
    generado_por_ia TINYINT(1) NOT NULL DEFAULT 1,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diseno_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE SET NULL,
    CONSTRAINT fk_diseno_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_diseno_runbook FOREIGN KEY (runbook_id) REFERENCES runbooks(id) ON DELETE SET NULL,
    CONSTRAINT fk_diseno_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Chat interno: reemplaza a WhatsApp como canal conversacional. Cada
-- mensaje del usuario se triagea igual que lo haría un agente externo
-- (Hermes) por WhatsApp, pero sin depender de credenciales de Meta.
-- ---------------------------------------------------------------------
ALTER TABLE tickets MODIFY COLUMN canal_origen
    ENUM('web', 'chat', 'whatsapp', 'slack', 'email', 'api') NOT NULL DEFAULT 'web';

CREATE TABLE IF NOT EXISTS chat_mensajes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    rol ENUM('usuario', 'asistente') NOT NULL,
    contenido TEXT NOT NULL,
    ticket_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_usuario (usuario_id),
    CONSTRAINT fk_chat_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
