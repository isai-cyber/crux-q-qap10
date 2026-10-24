-- Migración: onboarding de instalación
--  1) API de IA configurable por organización (antes solo vía .env global,
--     compartida por todos los tenants — ahora cada organización puede traer
--     su propia clave, con el .env como respaldo si no configuran nada).
--  2) Conexiones MCP externas: ORION puede consumir otros servidores MCP
--     (JSON-RPC 2.0) dados de alta desde el chat, no solo exponer los suyos.

ALTER TABLE organizaciones
    ADD COLUMN IF NOT EXISTS ai_provider VARCHAR(30) NULL AFTER logo_path,
    ADD COLUMN IF NOT EXISTS ai_api_key VARCHAR(255) NULL AFTER ai_provider,
    ADD COLUMN IF NOT EXISTS ai_model VARCHAR(60) NULL AFTER ai_api_key;

CREATE TABLE IF NOT EXISTS mcp_conexiones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    url VARCHAR(255) NOT NULL,
    token VARCHAR(255) NULL,
    herramientas_cache TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mcp_org_nombre (organizacion_id, nombre),
    CONSTRAINT fk_mcp_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_mcp_usuario FOREIGN KEY (creado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
