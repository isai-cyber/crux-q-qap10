-- Migración: multi-tenencia (micro SaaS)
-- Convierte el CMMS de una sola empresa a múltiples organizaciones aisladas
-- dentro de la misma base de datos. El catálogo Siemens y la base de
-- conocimiento (manuales) se mantienen GLOBALES y compartidos entre todas
-- las organizaciones (son datos de referencia del fabricante, no operativos
-- de un cliente); todo lo demás queda aislado por organizacion_id.
USE cmms_industrial;

CREATE TABLE organizaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    plan ENUM('trial', 'pro') NOT NULL DEFAULT 'trial',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organizaciones_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Organización por defecto para los datos ya existentes en esta base de prueba.
INSERT INTO organizaciones (id, nombre, slug, plan) VALUES (1, 'Organización Demo', 'demo', 'pro');

ALTER TABLE usuarios ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE usuarios SET organizacion_id = 1;
ALTER TABLE usuarios MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;
-- El email deja de ser único globalmente y pasa a serlo por organización
-- (dos empresas distintas pueden tener un usuario con el mismo correo).
ALTER TABLE usuarios DROP INDEX uq_usuarios_email;
ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_org_email (organizacion_id, email);

ALTER TABLE plantas ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE plantas SET organizacion_id = 1;
ALTER TABLE plantas MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE plantas ADD CONSTRAINT fk_plantas_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;

ALTER TABLE activos ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE activos SET organizacion_id = 1;
ALTER TABLE activos MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE activos ADD CONSTRAINT fk_activos_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;
ALTER TABLE activos ADD KEY idx_activos_org (organizacion_id);

ALTER TABLE tickets ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE tickets SET organizacion_id = 1;
ALTER TABLE tickets MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE tickets ADD CONSTRAINT fk_tickets_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;
ALTER TABLE tickets ADD KEY idx_tickets_org (organizacion_id);
-- El folio (TCK-2026-0001...) se numera por organización, no globalmente:
-- la restricción única debe incluir organizacion_id o dos tenants distintos
-- chocarán al generar el mismo folio (bug real encontrado en pruebas).
ALTER TABLE tickets DROP INDEX uq_tickets_folio;
ALTER TABLE tickets ADD UNIQUE KEY uq_tickets_org_folio (organizacion_id, folio);

ALTER TABLE ordenes_trabajo ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE ordenes_trabajo SET organizacion_id = 1;
ALTER TABLE ordenes_trabajo MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE ordenes_trabajo ADD CONSTRAINT fk_ordenes_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;

ALTER TABLE runbooks ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE runbooks SET organizacion_id = 1;
ALTER TABLE runbooks MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE runbooks ADD CONSTRAINT fk_runbooks_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;
ALTER TABLE runbooks ADD KEY idx_runbooks_org (organizacion_id);

ALTER TABLE disenos_tecnicos ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE disenos_tecnicos SET organizacion_id = 1;
ALTER TABLE disenos_tecnicos MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE disenos_tecnicos ADD CONSTRAINT fk_disenos_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;

ALTER TABLE api_tokens ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE api_tokens SET organizacion_id = 1;
ALTER TABLE api_tokens MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE api_tokens ADD CONSTRAINT fk_tokens_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;

ALTER TABLE chat_mensajes ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE chat_mensajes SET organizacion_id = 1;
ALTER TABLE chat_mensajes MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE chat_mensajes ADD CONSTRAINT fk_chat_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;

ALTER TABLE logs_ia ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE logs_ia SET organizacion_id = 1;
ALTER TABLE logs_ia MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE logs_ia ADD CONSTRAINT fk_logs_ia_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;

-- ---------------------------------------------------------------------
-- El inventario (existencias) pasa a ser por organización: el catálogo
-- Siemens (specs/precio) es compartido, pero el stock físico de cada
-- empresa es suyo. Se separa de productos_catalogo.
-- ---------------------------------------------------------------------
CREATE TABLE inventario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_inventario_org_producto (organizacion_id, producto_id),
    CONSTRAINT fk_inventario_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventario_producto FOREIGN KEY (producto_id) REFERENCES productos_catalogo(id) ON DELETE CASCADE,
    CONSTRAINT chk_inventario_no_negativo CHECK (stock_actual >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inventario (organizacion_id, producto_id, stock_actual)
    SELECT 1, id, stock_actual FROM productos_catalogo WHERE stock_actual > 0;

ALTER TABLE productos_catalogo DROP CONSTRAINT chk_stock_no_negativo;
ALTER TABLE productos_catalogo DROP COLUMN stock_actual;

ALTER TABLE movimientos_stock ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER id;
UPDATE movimientos_stock SET organizacion_id = 1;
ALTER TABLE movimientos_stock MODIFY COLUMN organizacion_id INT UNSIGNED NOT NULL;
ALTER TABLE movimientos_stock ADD CONSTRAINT fk_movstock_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE;
