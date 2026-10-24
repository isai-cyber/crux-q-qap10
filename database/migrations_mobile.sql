-- Migración: seguimiento de consumo de IA (tokens y costo) por organización.
-- Los proveedores compatibles con OpenAI/Anthropic reportan usage en cada
-- respuesta; AIService lo captura y se persiste en logs_ia.

ALTER TABLE logs_ia
    ADD COLUMN prompt_tokens INT UNSIGNED NULL AFTER latencia_ms,
    ADD COLUMN completion_tokens INT UNSIGNED NULL AFTER prompt_tokens,
    ADD INDEX idx_logs_ia_org_fecha (organizacion_id, created_at);
