-- Migración: órdenes de compra con aprobación (inspirado en Atlas CMMS,
-- adaptado a este stack). Antes el reabastecimiento era un registro directo
-- de movimiento de stock sin ningún control; esto agrega un flujo formal:
-- solicitar → aprobar/rechazar → recibir (ahí sí impacta el inventario).

CREATE TABLE IF NOT EXISTS ordenes_compra (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    folio VARCHAR(20) NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    notas TEXT NULL,
    estado ENUM('pendiente', 'aprobada', 'rechazada', 'recibida') NOT NULL DEFAULT 'pendiente',
    solicitado_por_id INT UNSIGNED NULL,
    aprobado_por_id INT UNSIGNED NULL,
    fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oc_org_folio (organizacion_id, folio),
    KEY idx_oc_org (organizacion_id),
    KEY idx_oc_estado (estado),
    CONSTRAINT fk_oc_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_oc_producto FOREIGN KEY (producto_id) REFERENCES productos_catalogo(id) ON DELETE CASCADE,
    CONSTRAINT fk_oc_solicitante FOREIGN KEY (solicitado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_oc_aprobador FOREIGN KEY (aprobado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
