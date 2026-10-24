-- Migración: Soporte para Stripe & Suscripciones en Crux A.I
ALTER TABLE organizaciones 
    ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(100) NULL AFTER plan,
    ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(100) NULL AFTER stripe_customer_id,
    ADD COLUMN IF NOT EXISTS stripe_subscription_status VARCHAR(50) NULL AFTER stripe_subscription_id;

CREATE TABLE IF NOT EXISTS pagos_stripe (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    stripe_session_id VARCHAR(150) NULL,
    stripe_charge_id VARCHAR(150) NULL,
    stripe_refund_id VARCHAR(150) NULL,
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    moneda VARCHAR(10) NOT NULL DEFAULT 'usd',
    estado ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'paid',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pagos_org (organizacion_id),
    CONSTRAINT fk_pagos_org FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;
