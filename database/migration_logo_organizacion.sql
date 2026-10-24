-- Migración: whitelabel — cada organización puede subir su propio logo.
ALTER TABLE organizaciones ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) NULL AFTER nombre;
