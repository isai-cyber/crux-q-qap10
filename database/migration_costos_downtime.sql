-- Migración: costos por orden de trabajo + tiempo de inactividad por activo
-- (inspirado en Atlas CMMS, adaptado a este stack). El costo de repuestos
-- se calcula al vuelo desde orden_trabajo_repuesto + productos_catalogo.precio_lista,
-- así que solo hace falta un campo nuevo para la mano de obra.

ALTER TABLE ordenes_trabajo
    ADD COLUMN IF NOT EXISTS costo_mano_obra DECIMAL(10,2) NULL AFTER tiempo_invertido_minutos;

CREATE TABLE IF NOT EXISTS activos_downtime (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    activo_id INT UNSIGNED NOT NULL,
    inicio DATETIME NOT NULL,
    fin DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_downtime_org (organizacion_id),
    KEY idx_downtime_activo (activo_id),
    CONSTRAINT fk_downtime_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_downtime_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
