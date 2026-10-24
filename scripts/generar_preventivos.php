<?php

declare(strict_types=1);

/**
 * Genera los tickets de mantenimiento preventivo vencidos de todas las
 * organizaciones. Pensado para correr por cron (ej. diario a medianoche):
 *   0 0 * * * php /ruta/al/proyecto/scripts/generar_preventivos.php
 *
 * No es obligatorio: PlanMantenimientoController::index() ya genera los
 * vencidos de forma perezosa al visitar /mantenimiento-preventivo, así que
 * el sistema funciona igual sin cron configurado (solo que la generación
 * ocurre cuando alguien abre esa pantalla, no a medianoche en punto).
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$organizaciones = Database::connection()->query('SELECT id, nombre FROM organizaciones WHERE activo = 1')->fetchAll();

$totalGenerados = 0;
foreach ($organizaciones as $org) {
    $generados = PlanMantenimientoModel::generarPendientes((int) $org['id']);
    if ($generados) {
        echo "[{$org['nombre']}] " . count($generados) . " ticket(s) preventivo(s) generado(s): "
            . implode(', ', array_column($generados, 'folio')) . "\n";
        $totalGenerados += count($generados);
    }
}

echo "Listo. {$totalGenerados} ticket(s) generado(s) en total.\n";
