-- Datos iniciales para arrancar el sistema
USE cmms_industrial;

INSERT INTO organizaciones (nombre, slug, plan) VALUES
    ('Organización Demo', 'demo', 'pro');

INSERT INTO plantas (organizacion_id, nombre, ubicacion) VALUES
    (1, 'Planta Demo', 'Sin especificar');

-- Usuario administrador inicial. Usuario: admin · Password: CambiaEstaClave123!
-- IMPORTANTE: cambiar esta contraseña en el primer inicio de sesión.
INSERT INTO usuarios (organizacion_id, nombre, usuario, email, password_hash, rol, planta_id, activo) VALUES
    (1, 'Administrador', 'admin', 'admin@example.com', '$2y$12$R0.JjVWWmNu7g7dQURFjbefXar8RAg7eQNgNJzU8nD8yUCmQhZTBC', 'admin', 1, 1);

-- Catálogo Siemens: GLOBAL, no lleva organizacion_id (se comparte entre todos los tenants).
INSERT INTO categorias_producto (nombre, descripcion) VALUES
    ('SIRIUS Control', 'Arrancadores, contactores, relés de control'),
    ('IEC Power Control', 'Guardamotores y accesorios IEC'),
    ('SIRIUS Hybrid', 'Arrancadores híbridos y motor starters'),
    ('Solid State Control', 'Relés de estado sólido y soft starters');

INSERT INTO productos_catalogo (numero_parte, categoria_id, descripcion, precio_lista, vigencia_catalogo) VALUES
    ('3RT2015-1BB41', 1, 'Contactor SIRIUS, 3 polos, 7A, bobina 24V DC', 45.00, 'enero 2026'),
    ('3RV2011-1AA10', 2, 'Guardamotor SIRIUS, rango 1-1.6A', 62.50, 'enero 2026'),
    ('3RW3014-1BB14', 4, 'Arrancador suave SIRIUS, 5.5kW, 400V', 210.00, 'enero 2026');
