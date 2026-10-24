-- CMMS Industrial con IA — Esquema de base de datos (multi-tenant / micro SaaS)
-- MariaDB 10.6+, InnoDB, utf8mb4
--
-- Modelo de datos: "organizaciones" son los clientes (tenants) del SaaS.
-- El catálogo Siemens (productos_catalogo/categorias_producto) y la base de
-- conocimiento de manuales (base_conocimiento_chunks) son GLOBALES y se
-- comparten entre todas las organizaciones (son datos de referencia del
-- fabricante, no datos operativos de un cliente). Todo lo demás
-- (usuarios, plantas, activos, tickets, runbooks, inventario, etc.) está
-- aislado por organizacion_id: cada consulta de la aplicación debe filtrar
-- siempre por la organización del usuario autenticado.

CREATE DATABASE IF NOT EXISTS cmms_industrial
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cmms_industrial;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Organizaciones (tenants del SaaS)
-- ---------------------------------------------------------------------
CREATE TABLE organizaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    logo_path VARCHAR(255) NULL,
    ai_provider VARCHAR(30) NULL,
    ai_api_key VARCHAR(255) NULL,
    ai_model VARCHAR(60) NULL,
    slug VARCHAR(80) NOT NULL,
    plan ENUM('trial', 'pro', 'enterprise') NOT NULL DEFAULT 'trial',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_organizaciones_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE mcp_conexiones (
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

-- ---------------------------------------------------------------------
-- Plantas
-- ---------------------------------------------------------------------
CREATE TABLE plantas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    ubicacion VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_plantas_org (organizacion_id),
    CONSTRAINT fk_plantas_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Usuarios
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    usuario VARCHAR(60) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'supervisor', 'tecnico') NOT NULL DEFAULT 'tecnico',
    planta_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_org_usuario (organizacion_id, usuario),
    UNIQUE KEY uq_usuarios_org_email (organizacion_id, email),
    CONSTRAINT fk_usuarios_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuarios_planta FOREIGN KEY (planta_id) REFERENCES plantas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Catálogo GLOBAL (fuente: ic21-cppc-06000-0921.pdf) — compartido entre
-- todas las organizaciones. El stock físico de cada una vive en "inventario".
-- ---------------------------------------------------------------------
CREATE TABLE categorias_producto (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    categoria_padre_id INT UNSIGNED NULL,
    descripcion VARCHAR(255) NULL,
    CONSTRAINT fk_categoria_padre FOREIGN KEY (categoria_padre_id) REFERENCES categorias_producto(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE productos_catalogo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_parte VARCHAR(80) NOT NULL,
    categoria_id INT UNSIGNED NULL,
    descripcion VARCHAR(500) NOT NULL,
    especificaciones JSON NULL,
    precio_lista DECIMAL(12,2) NULL,
    vigencia_catalogo VARCHAR(20) NULL COMMENT 'Ej: enero 2026',
    origen_documento VARCHAR(120) NULL DEFAULT 'ic21-cppc-06000-0921',
    embedding LONGTEXT NULL COMMENT 'Vector JSON para búsqueda semántica',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_producto_numero_parte (numero_parte),
    KEY idx_producto_categoria (categoria_id),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_producto(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Inventario: el stock físico de repuestos es propio de cada organización.
CREATE TABLE inventario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_inventario_org_producto (organizacion_id, producto_id),
    CONSTRAINT fk_inventario_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventario_producto FOREIGN KEY (producto_id) REFERENCES productos_catalogo(id) ON DELETE CASCADE,
    CONSTRAINT chk_inventario_no_negativo CHECK (stock_actual >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Activos / CMDB (modelo inspirado en SAT_UserGuide_es-ES.pdf)
-- ---------------------------------------------------------------------
CREATE TABLE activos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(100) NULL,
    planta_id INT UNSIGNED NOT NULL,
    ubicacion VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    nombre_profinet VARCHAR(120) NULL,
    version_firmware VARCHAR(60) NULL,
    fecha_puesta_marcha DATE NULL,
    estado ENUM('operativo', 'en_mantenimiento', 'fuera_servicio') NOT NULL DEFAULT 'operativo',
    foto_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_activos_org (organizacion_id),
    KEY idx_activos_planta (planta_id),
    CONSTRAINT fk_activos_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_activos_planta FOREIGN KEY (planta_id) REFERENCES plantas(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE activo_producto (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activo_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_activo_producto (activo_id, producto_id),
    CONSTRAINT fk_ap_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_producto FOREIGN KEY (producto_id) REFERENCES productos_catalogo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE diagnosticos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activo_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(60) NULL,
    severidad ENUM('info', 'advertencia', 'critico') NOT NULL DEFAULT 'info',
    descripcion TEXT NOT NULL,
    origen ENUM('manual', 'automatico') NOT NULL DEFAULT 'manual',
    fecha_diagnostico DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_diagnostico_activo (activo_id),
    CONSTRAINT fk_diagnostico_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Runbooks y base de conocimiento (IA)
-- base_conocimiento_chunks es GLOBAL (los manuales son los mismos para
-- todas las organizaciones); runbooks es por organización (cada empresa
-- aprende de sus propios tickets).
-- ---------------------------------------------------------------------
CREATE TABLE runbooks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    sintoma TEXT NOT NULL,
    causa TEXT NULL,
    solucion TEXT NOT NULL,
    activo_id INT UNSIGNED NULL,
    generado_por ENUM('ia', 'tecnico') NOT NULL DEFAULT 'tecnico',
    validado TINYINT(1) NOT NULL DEFAULT 0,
    vigente TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se pone en 0 automáticamente si su tasa de fallo es alta',
    veces_aplicado INT UNSIGNED NOT NULL DEFAULT 0,
    veces_exitoso INT UNSIGNED NOT NULL DEFAULT 0,
    veces_fallido INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_runbooks_org (organizacion_id),
    CONSTRAINT fk_runbooks_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_runbook_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE runbook_aplicaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    runbook_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    resultado ENUM('exitoso', 'fallido') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_runbook_aplicacion_runbook FOREIGN KEY (runbook_id) REFERENCES runbooks(id) ON DELETE CASCADE,
    CONSTRAINT fk_runbook_aplicacion_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE base_conocimiento_chunks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento_origen VARCHAR(150) NOT NULL COMMENT 'Ej: BE2_es_es-ES.pdf',
    capitulo VARCHAR(150) NULL,
    texto LONGTEXT NOT NULL,
    embedding LONGTEXT NULL COMMENT 'Vector JSON',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chunk_documento (documento_origen)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tickets y órdenes de trabajo
-- ---------------------------------------------------------------------
CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    folio VARCHAR(20) NOT NULL,
    activo_id INT UNSIGNED NULL,
    reportado_por_id INT UNSIGNED NULL,
    canal_origen ENUM('web', 'chat', 'whatsapp', 'slack', 'email', 'api', 'sistema') NOT NULL DEFAULT 'web',
    tipo_mantenimiento ENUM('correctivo', 'preventivo') NOT NULL DEFAULT 'correctivo',
    descripcion TEXT NOT NULL,
    categoria_ia VARCHAR(60) NULL,
    categoria_final VARCHAR(60) NULL,
    prioridad_ia ENUM('baja', 'media', 'alta', 'critica') NULL,
    prioridad_final ENUM('baja', 'media', 'alta', 'critica') NULL,
    estado ENUM('abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado') NOT NULL DEFAULT 'abierto',
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tickets_org_folio (organizacion_id, folio),
    KEY idx_tickets_org (organizacion_id),
    KEY idx_tickets_estado (estado),
    KEY idx_tickets_activo (activo_id),
    CONSTRAINT fk_tickets_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_usuario FOREIGN KEY (reportado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE planes_mantenimiento (
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

CREATE TABLE ordenes_trabajo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NULL,
    runbook_id INT UNSIGNED NULL COMMENT 'Runbook aplicado para resolver, si se usó uno',
    diagnostico TEXT NULL,
    solucion_aplicada TEXT NULL,
    solucion_exitosa TINYINT(1) NULL COMMENT 'Retroalimentación: ¿la solución funcionó? Alimenta la autoevolución de runbooks',
    tiempo_invertido_minutos INT UNSIGNED NULL,
    costo_mano_obra DECIMAL(10,2) NULL,
    estado ENUM('pendiente', 'en_progreso', 'completada') NOT NULL DEFAULT 'pendiente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orden_ticket (ticket_id),
    CONSTRAINT fk_orden_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_orden_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_orden_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_orden_runbook FOREIGN KEY (runbook_id) REFERENCES runbooks(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activos_downtime (
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

CREATE TABLE orden_trabajo_repuesto (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_trabajo_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_otr_orden FOREIGN KEY (orden_trabajo_id) REFERENCES ordenes_trabajo(id) ON DELETE CASCADE,
    CONSTRAINT fk_otr_producto FOREIGN KEY (producto_id) REFERENCES productos_catalogo(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE movimientos_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'salida') NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    orden_trabajo_id INT UNSIGNED NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_movimiento_org (organizacion_id),
    KEY idx_movimiento_producto (producto_id),
    CONSTRAINT fk_movstock_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (producto_id) REFERENCES productos_catalogo(id) ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_orden FOREIGN KEY (orden_trabajo_id) REFERENCES ordenes_trabajo(id) ON DELETE SET NULL,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Órdenes de compra (reabastecimiento con aprobación)
-- ---------------------------------------------------------------------
CREATE TABLE ordenes_compra (
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

-- ---------------------------------------------------------------------
-- Diseños técnicos generados por IA (diagramas SVG y documentos técnicos)
-- ---------------------------------------------------------------------
CREATE TABLE disenos_tecnicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    tipo ENUM('diagrama', 'documento') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion_solicitud TEXT NOT NULL,
    contenido LONGTEXT NOT NULL COMMENT 'Markup SVG o texto Markdown según "formato"',
    formato ENUM('svg', 'markdown') NOT NULL,
    activo_id INT UNSIGNED NULL,
    ticket_id INT UNSIGNED NULL,
    runbook_id INT UNSIGNED NULL,
    generado_por_ia TINYINT(1) NOT NULL DEFAULT 1,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diseno_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_diseno_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE SET NULL,
    CONSTRAINT fk_diseno_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT fk_diseno_runbook FOREIGN KEY (runbook_id) REFERENCES runbooks(id) ON DELETE SET NULL,
    CONSTRAINT fk_diseno_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Chat interno (asistente "ORION"): canal conversacional propio de la
-- plataforma, alternativa a WhatsApp que no depende de credenciales de Meta.
-- ---------------------------------------------------------------------
CREATE TABLE chat_mensajes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    rol ENUM('usuario', 'asistente') NOT NULL,
    contenido TEXT NOT NULL,
    ticket_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_org (organizacion_id),
    KEY idx_chat_usuario (usuario_id),
    CONSTRAINT fk_chat_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Integración con agentes externos (Hermes, MCP u otros) y auditoría de IA
-- ---------------------------------------------------------------------
CREATE TABLE api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    permisos JSON NOT NULL,
    usuario_creador_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_uso DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_token_hash (token_hash),
    CONSTRAINT fk_tokens_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_token_usuario FOREIGN KEY (usuario_creador_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE logs_ia (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(120) NOT NULL,
    entrada LONGTEXT NULL,
    salida LONGTEXT NULL,
    modelo VARCHAR(60) NULL,
    costo_estimado DECIMAL(10,4) NULL,
    latencia_ms INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_ia_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_log_ia_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
