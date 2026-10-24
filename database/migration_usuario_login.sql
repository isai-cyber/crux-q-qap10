-- Migración: login por nombre de usuario en vez de email.
-- El email se conserva como dato de contacto; "usuario" pasa a ser el
-- identificador de inicio de sesión (único por organización).

ALTER TABLE usuarios ADD COLUMN usuario VARCHAR(60) NULL AFTER nombre;

UPDATE usuarios SET usuario = LOWER(SUBSTRING_INDEX(email, '@', 1));

-- Desempata usuarios que hubieran quedado con el mismo valor derivado
-- dentro de la misma organización, añadiendo el id al duplicado.
UPDATE usuarios u
JOIN (
    SELECT organizacion_id, usuario, MIN(id) AS keep_id
    FROM usuarios
    GROUP BY organizacion_id, usuario
    HAVING COUNT(*) > 1
) dup ON dup.organizacion_id = u.organizacion_id AND dup.usuario = u.usuario AND u.id <> dup.keep_id
SET u.usuario = CONCAT(u.usuario, '-', u.id);

ALTER TABLE usuarios MODIFY usuario VARCHAR(60) NOT NULL;
ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_org_usuario (organizacion_id, usuario);
