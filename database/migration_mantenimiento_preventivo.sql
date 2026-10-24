-- Migración: mantenimiento preventivo programado (inspirado en Atlas CMMS,
-- adaptado a este stack). Hasta ahora el sistema era 100% reactivo (solo
-- tickets por falla reportada); esto agrega tareas recurrentes por
-- calendario que generan tickets/órdenes automáticamente.

ALTER TABLE tickets
    MODIFY COLUMN canal_origen ENUM('web', 'chat', 'whatsapp', 'slack', 'email', 'api', 'sistema') NOT NULL DEFAULT 'web',
    ADD COLUMN IF NOT EXISTS tipo_mantenimiento ENUM('correctivo', 'preventivo') NOT NULL DEFAULT 'correctivo' AFTER canal_origen;

CREATE TABLE IF NOT EXISTS planes_mantenimiento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    activo_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    frecuencia_dias INT UNSIGNED NOT NULL,
    proxima_fecha DATE NOT NULL,
    ultima_generacion DATE NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pm_org (organizacion_id),
    KEY idx_pm_proxima (proxima_fecha),
    CONSTRAINT fk_pm_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_usuario FOREIGN KEY (creado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
