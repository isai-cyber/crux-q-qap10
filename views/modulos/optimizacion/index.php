<?php $pageTitle = 'Optimización cuántica · QAP10'; require __DIR__ . '/../../layouts/header.php'; ?>

<?php
/** @var array|null $r  Resultados de quantum/results/results.json */
/** @var bool $puedeEjecutar */

$num = fn ($v, int $dec = 0) => $v === null ? '—' : number_format((float) $v, $dec, ',', '.');
$pct = fn ($v, int $dec = 1) => $v === null ? '—' : number_format(100 * (float) $v, $dec, ',', '.') . ' %';
$seg = function ($v): string {
    if ($v === null) return '—';
    $v = (float) $v;
    return $v < 0.01 ? number_format($v * 1000, 1, ',', '.') . ' ms' : number_format($v, 2, ',', '.') . ' s';
};
$bytes = function (float $b): string {
    $u = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return $b > 1e6 ? sprintf('%.1e %s', $b, $u[$i]) : number_format($b, 0, ',', '.') . ' ' . $u[$i];
};

// Narrativa (track abierto · manufactura / mantenimiento): nombres ilustrativos.
$activos = ['Torno CNC', 'Fresadora 5 ejes', 'Prensa hidráulica', 'Robot de soldadura', 'Cabina de pintura',
            'Línea de ensamble', 'Inspección QA', 'Compresor central', 'Almacén de repuestos', 'Empaque'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h2 class="mb-1">Optimización cuántica · Reto QAP10</h2>
        <p class="text-secondary mb-0">NTT DATA · Qiskit Fall Fest Lima — asignación cuadrática con métodos clásicos, QAOA (Qiskit) e híbridos.</p>
    </div>
    <span class="badge bg-light text-dark border"><i class="bi bi-cpu"></i> Crux Q · motor <code>quantum/</code></span>
</div>

<?php if (!$r): ?>
    <div class="alert alert-warning">
        Aún no hay resultados. Ejecuta <code>cd quantum && .venv/bin/python run_experiments.py</code> (ver <code>quantum/README.md</code>).
    </div>
<?php require __DIR__ . '/../../layouts/footer.php'; return; endif; ?>

<?php
$m = $r['modelo'];
$F = $m['F'];
$D = $m['D'];
$n = count($F);
$optimo = (float) $r['optimo_referencia'];
$permOpt = $m['perm_optima'];

// Rejilla de la planta: D resulta ser la distancia Manhattan sobre estas coordenadas.
$coords = [[0, 0], [1, 0], [2, 0], [3, 0], [0, 1], [1, 1], [2, 1], [3, 1], [0, 2], [1, 2]];
$esManhattan = true;
for ($a = 0; $a < $n; $a++) {
    for ($b = 0; $b < $n; $b++) {
        $dm = abs($coords[$a][0] - $coords[$b][0]) + abs($coords[$a][1] - $coords[$b][1]);
        if ((int) $D[$a][$b] !== $dm) { $esManhattan = false; }
    }
}

// Costo esperado de una asignación al azar: sum_{i<k} F_ik * media(D fuera de la diagonal).
$sumF = 0; $sumD = 0;
for ($a = 0; $a < $n; $a++) {
    for ($b = 0; $b < $n; $b++) {
        if ($a < $b) $sumF += $F[$a][$b];
        if ($a !== $b) $sumD += $D[$a][$b];
    }
}
$costoAzar = $sumF * $sumD / ($n * ($n - 1));

$c = $r['completa'];
$mejorLnsQaoa = null; $runLnsQaoa = null;
foreach ($c['lns_qaoa'] as $k => $bloque) {
    foreach ($bloque['runs'] as $run) {
        if ($mejorLnsQaoa === null || $run['costo'] < $mejorLnsQaoa) { $mejorLnsQaoa = $run['costo']; $runLnsQaoa = $run; }
    }
}
$gap = fn ($v) => $v === null ? null : ($v - $optimo) / $optimo;
$e1 = $r['etapa1'];

// Mejor corrida QAOA por (N, mezclador) en la Etapa 1
$qaoaResumen = [];
foreach ($e1 as $N => $bloque) {
    foreach ($bloque['qaoa'] as $q) {
        $key = $N . '|' . $q['mixer'];
        $qaoaResumen[$key]['runs'][] = $q;
    }
}
$agg = function (array $runs, string $campo): array {
    $v = array_column($runs, $campo);
    return ['media' => array_sum($v) / max(count($v), 1), 'max' => max($v), 'min' => min($v)];
};
?>

<div class="alert alert-info d-flex gap-2">
    <i class="bi bi-question-circle fs-5"></i>
    <div>
        <strong>Pregunta del reto:</strong> ¿hasta dónde ayudan los métodos cuánticos o híbridos a encontrar buenas asignaciones
        con tiempo y cómputo limitados? Aquí no afirmamos ventaja cuántica: medimos qué funciona, qué no y por qué.
        Todos los circuitos se <strong>simulan</strong> en CPU con Qiskit Aer; no se usó hardware cuántico.
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-success h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold">Óptimo verificado (N=10)</span>
                <span class="kpi-icon verde"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="kpi-value"><?= $num($m['optimo_enumerado']) ?></div>
            <small class="text-secondary">Enumeración de 10! en <?= $seg($m['tiempo_enumeracion_s']) ?></small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-primary h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold">Mejor híbrido LNS + QAOA</span>
                <span class="kpi-icon azul"><i class="bi bi-cpu"></i></span>
            </div>
            <div class="kpi-value"><?= $num($mejorLnsQaoa) ?></div>
            <small class="text-secondary">gap <?= $pct($gap($mejorLnsQaoa)) ?> · sub-QAP de <?= (int) ($runLnsQaoa['qubits_por_subproblema'] ?? 0) ?> qubits</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-warning h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold">Muestras factibles N=4</span>
                <span class="kpi-icon ambar"><i class="bi bi-funnel"></i></span>
            </div>
            <div class="kpi-value"><?= $pct($agg($qaoaResumen['4|xy']['runs'], 'fraccion_factible')['media'], 0) ?></div>
            <small class="text-secondary">QAOA-XY vs <?= $pct($agg($qaoaResumen['4|x']['runs'], 'fraccion_factible')['media'], 2) ?> con QAOA estándar</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-secondary h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-secondary small fw-semibold">Recocido simulado (clásico)</span>
                <span class="kpi-icon gris"><i class="bi bi-thermometer-half"></i></span>
            </div>
            <div class="kpi-value"><?= $num($c['recocido']['resumen']['mejor']) ?></div>
            <small class="text-secondary">media <?= $num($c['recocido']['resumen']['media'], 1) ?> en <?= (int) $c['recocido']['resumen']['n'] ?> semillas</small>
        </div></div>
    </div>
</div>

<!-- Contexto -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-buildings text-primary"></i> Contexto · Track abierto: distribución de planta en un CMMS</div>
            <div class="card-body">
                <p>Crux A.I gestiona el mantenimiento de una planta. Cada orden de trabajo mueve técnicos, herramientas y repuestos
                    entre activos. Proponemos usar QAP10 para decidir <strong>en qué bahía de la planta se instala cada uno de 10 activos</strong>
                    (por ejemplo, al rediseñar el layout tras una ampliación).</p>
                <ul class="mb-3">
                    <li><strong>F<sub>ik</sub></strong>: traslados de técnicos o repuestos entre los activos <em>i</em> y <em>k</em> por semana (en Crux saldría del historial de órdenes de trabajo).</li>
                    <li><strong>D<sub>jl</sub></strong>: distancia por pasillos entre las bahías <em>j</em> y <em>l</em>.
                        <?= $esManhattan ? 'En qap10, D coincide exactamente con la distancia Manhattan en una rejilla de 4 + 4 + 2 bahías (verificado), lo que hace natural esta lectura.' : '' ?></li>
                    <li><strong>Decisión</strong>: una bahía por activo para reducir la distancia total ponderada que recorren los equipos de mantenimiento.</li>
                </ul>
                <p class="small text-secondary mb-0">Los datos de qap10 son un benchmark matemático, no mediciones de una planta. Los nombres de activos son ilustrativos.
                    La misma matemática vale para el Track A (vuelos → puertas) o el Track B (rutas eléctricas → estaciones).</p>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calculator text-primary"></i> Estimación de impacto (supuestos, no mediciones)</div>
            <div class="card-body small">
                <?php
                $metrosPorUnidad = 15;      // supuesto: 1 unidad de D = 15 m de pasillo
                $semanas = 48;              // supuesto: semanas operativas por año
                $costoMetro = 0.05;         // supuesto: USD por metro recorrido (tiempo de técnico)
                $ahorroUnidades = $costoAzar - $optimo;
                $ahorroAnual = $ahorroUnidades * $metrosPorUnidad * $semanas * $costoMetro;
                ?>
                <table class="table table-sm mb-2">
                    <tr><td>Costo esperado de un layout al azar</td><td class="text-end"><?= $num($costoAzar, 1) ?></td></tr>
                    <tr><td>Costo óptimo</td><td class="text-end"><?= $num($optimo) ?></td></tr>
                    <tr><td>Reducción</td><td class="text-end fw-bold"><?= $num($ahorroUnidades, 1) ?> (<?= $pct($ahorroUnidades / $costoAzar, 0) ?>)</td></tr>
                    <tr><td>Supuestos: 1 u. de D = <?= $metrosPorUnidad ?> m · <?= $semanas ?> semanas/año · $<?= $costoMetro ?>/m</td><td></td></tr>
                    <tr class="table-light"><td>Ahorro anual estimado</td><td class="text-end fw-bold">≈ $<?= $num($ahorroAnual) ?></td></tr>
                </table>
                <p class="text-secondary mb-0">Cálculo: (C<sub>azar</sub> − C*) × m/u × semanas × $/m, donde F se lee en traslados por semana.
                    El costo al azar es Σ<sub>i&lt;k</sub>F<sub>ik</sub> · media(D<sub>j≠l</sub>). Validarlo exigiría datos reales de órdenes de trabajo y planos.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modelo y datos -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-file-earmark-code text-primary"></i> Modelo y lectura de datos</div>
            <div class="card-body small">
                <p>Del archivo <code><?= e($m['archivo']) ?></code> (linealización tipo Adams-Johnson) se leen
                    <?= (int) $m['variables_x'] ?> variables x<sub>ij</sub> y <?= $num($m['variables_y']) ?> variables y = x<sub>ij</sub>x<sub>kl</sub>.
                    Cada y se enlaza con sus dos x siguiendo sus filas de restricción; el coeficiente de NOBJ da c<sub>ijkl</sub>.
                    La matriz c[(i,k),(j,l)] es de rango 1, así que c<sub>ijkl</sub> = F<sub>ik</sub>·D<sub>jl</sub> (escala: min D = 1).</p>
                <ul class="list-unstyled mb-0">
                    <?php
                    $checks = [
                        ['Error de la factorización F·D', $m['error_factorizacion'] == 0, $num($m['error_factorizacion'], 3)],
                        ['C(perm*) con F, D = suma directa de coeficientes MPS', abs($m['costo_FD_en_perm_optima'] - $m['costo_MPS_directo_en_perm_optima']) < 1e-9,
                            $num($m['costo_FD_en_perm_optima']) . ' = ' . $num($m['costo_MPS_directo_en_perm_optima'])],
                        ['Óptimo enumerado = C* de referencia', abs($m['optimo_enumerado'] - $optimo) < 1e-9, $num($m['optimo_enumerado']) . ' = ' . $num($optimo)],
                    ];
                    foreach ($m['controles'] as $N => $ctl) {
                        $checks[] = ["Subinstancia N={$N}: óptimo = control", $ctl['coincide'], $num($ctl['optimo_calculado']) . ' = ' . $num($ctl['optimo_control'])];
                    }
                    $checks[] = ['D simétrica, pares contados una vez (i<k)', (bool) $m['D_simetrica'], 'sí'];
                    foreach ($checks as [$txt, $ok, $val]): ?>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i> <?= e($txt) ?></span>
                            <code><?= e($val) ?></code>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-grid-3x3 text-primary"></i> Asignación óptima en la rejilla de la planta (C = <?= $num($optimo) ?>)</span>
                <span class="small text-secondary">líneas = flujo F entre activos</span>
            </div>
            <div class="card-body">
                <?php
                $cell = 128; $pad = 18; $w = 4 * $cell + 2 * $pad; $h = 3 * $cell * 0.62 + 2 * $pad;
                $pos = function (int $loc) use ($coords, $cell, $pad) {
                    return [$pad + $coords[$loc][0] * $cell + $cell / 2, $pad + $coords[$loc][1] * $cell * 0.62 + $cell * 0.31];
                };
                $maxF = 0;
                foreach ($F as $row) { $maxF = max($maxF, max($row)); }
                ?>
                <svg viewBox="0 0 <?= $w ?> <?= (int) $h ?>" class="w-100" role="img" aria-label="Layout óptimo">
                    <?php for ($i = 0; $i < $n; $i++): for ($k = $i + 1; $k < $n; $k++): if ($F[$i][$k] > 0):
                        [$x1, $y1] = $pos($permOpt[$i]); [$x2, $y2] = $pos($permOpt[$k]); ?>
                        <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>"
                              stroke="var(--brand-blue)" stroke-opacity="<?= round(0.15 + 0.6 * $F[$i][$k] / $maxF, 2) ?>"
                              stroke-width="<?= round(1 + 5 * $F[$i][$k] / $maxF, 1) ?>"><title><?= e($activos[$i] . ' ↔ ' . $activos[$k] . ': F=' . $F[$i][$k]) ?></title></line>
                    <?php endif; endfor; endfor; ?>
                    <?php for ($i = 0; $i < $n; $i++): [$x, $y] = $pos($permOpt[$i]); ?>
                        <rect x="<?= $x - 56 ?>" y="<?= $y - 30 ?>" width="112" height="60" rx="10" fill="var(--surface)" stroke="var(--brand-navy)" stroke-width="1.5"/>
                        <text x="<?= $x ?>" y="<?= $y - 11 ?>" text-anchor="middle" font-size="11" fill="var(--text-soft)" font-family="var(--font-mono)">Bahía <?= $permOpt[$i] + 1 ?></text>
                        <text x="<?= $x ?>" y="<?= $y + 6 ?>" text-anchor="middle" font-size="12" font-weight="700" fill="var(--brand-navy)">A<?= $i + 1 ?></text>
                        <text x="<?= $x ?>" y="<?= $y + 21 ?>" text-anchor="middle" font-size="9.5" fill="var(--text-muted)"><?= e($activos[$i]) ?></text>
                    <?php endfor; ?>
                </svg>
                <p class="small text-secondary mb-0">perm* = [<?= e(implode(', ', $permOpt)) ?>] (elemento i → ubicación perm[i], índices desde 0).</p>
            </div>
        </div>
    </div>
</div>

<!-- Etapa 1 -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-1-circle text-primary"></i> Etapa 1 · Subinstancias N = 3 y N = 4 (primeras N filas/columnas)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>N</th><th>Método</th><th class="text-end">Qubits</th><th class="text-end">Mejor costo factible</th>
                    <th class="text-end">Fracción factible</th><th class="text-end">P(óptimo)</th><th class="text-end">Puertas 2q</th><th class="text-end">Tiempo</th>
                </tr></thead>
                <tbody>
                <?php foreach ($e1 as $N => $b): $az = $b['azar']; ?>
                    <tr class="table-light"><td colspan="8" class="small fw-semibold">
                        N = <?= (int) $N ?> · <?= (int) $b['variables_qubo'] ?> variables QUBO · <?= (int) $b['permutaciones'] ?> permutaciones ·
                        <?= (int) $b['num_optimos'] ?> asignaciones óptimas · control <?= $num($b['optimo_control']) ?> · λ = <?= $num($b['lambda_por_defecto']) ?>
                    </td></tr>
                    <tr>
                        <td><?= (int) $N ?></td><td>Enumeración exacta (clásico)</td><td class="text-end">—</td>
                        <td class="text-end fw-bold"><?= $num($b['clasico']['enumeracion']['costo']) ?></td>
                        <td class="text-end">100 %</td><td class="text-end">—</td><td class="text-end">—</td>
                        <td class="text-end"><?= $seg($b['clasico']['enumeracion']['tiempo_s']) ?></td>
                    </tr>
                    <tr>
                        <td><?= (int) $N ?></td><td>Recocido simulado (5 semillas)</td><td class="text-end">—</td>
                        <td class="text-end"><?= $num($b['clasico']['recocido_resumen']['mejor']) ?> <span class="text-secondary small">(media <?= $num($b['clasico']['recocido_resumen']['media'], 1) ?>)</span></td>
                        <td class="text-end">100 %</td><td class="text-end">—</td><td class="text-end">—</td>
                        <td class="text-end"><?= $seg(array_sum(array_column($b['clasico']['recocido'], 'tiempo_s')) / 5) ?></td>
                    </tr>
                    <?php
                    $porConfig = [];
                    foreach ($b['qaoa'] as $q) { $porConfig[$q['mixer'] . '|' . $q['reps']][] = $q; }
                    foreach ($porConfig as $cfg => $runs):
                        [$mix, $p] = explode('|', $cfg);
                        $costos = array_filter(array_column($runs, 'costo'), fn ($v) => $v !== null);
                        $ff = $agg($runs, 'fraccion_factible'); $po = $agg($runs, 'prob_optimo'); $tt = $agg($runs, 'tiempo_s');
                    ?>
                        <tr>
                            <td><?= (int) $N ?></td>
                            <td>QAOA <?= $mix === 'xy' ? '<span class="badge bg-primary">mezclador XY</span>' : '<span class="badge bg-secondary">estándar (RX)</span>' ?> p=<?= (int) $p ?> <span class="text-secondary small">(<?= count($runs) ?> semillas)</span></td>
                            <td class="text-end"><?= (int) $runs[0]['qubits'] ?></td>
                            <td class="text-end <?= $costos && min($costos) == $b['optimo_control'] ? 'fw-bold text-success' : 'text-danger' ?>">
                                <?= $costos ? $num(min($costos)) : 'ninguno' ?>
                            </td>
                            <td class="text-end"><?= $pct($ff['media']) ?></td>
                            <td class="text-end"><?= $pct($po['media'], 2) ?></td>
                            <td class="text-end"><?= (int) $runs[0]['puertas_2q'] ?></td>
                            <td class="text-end"><?= $seg($tt['media']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="small text-secondary">
                        <td></td><td colspan="3">Referencia: muestrear al azar (bits uniformes / un 1 por fila)</td>
                        <td class="text-end"><?= $pct($az['uniforme_bits']['factible'], 2) ?> / <?= $pct($az['uniforme_por_fila_W']['factible']) ?></td>
                        <td class="text-end"><?= $pct($az['uniforme_bits']['optima'], 2) ?> / <?= $pct($az['uniforme_por_fila_W']['optima'], 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-secondary mb-0">
            Fracción factible y P(óptimo) se miden en 8 192 disparos finales con los parámetros optimizados.
            «Mejor costo factible» es el mejor costo real C(x) entre las muestras válidas, no la energía penalizada del QUBO.
            El mezclador XY parte de estados W por fila y conserva «un 1 por fila», así que solo la restricción de columnas queda penalizada.
        </p>
    </div>
</div>

<!-- λ -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-sliders text-primary"></i> Elección de λ (penalización)</div>
            <div class="card-body small">
                <p>λ por defecto = max<sub>i</sub> Σ<sub>k</sub>F<sub>ik</sub> · max D + 1: mover o quitar una asignación no puede ahorrar más que eso,
                    así que con este valor ninguna violación de restricción compensa. El barrido comprueba, enumerando los 2<sup>N²</sup> estados, si el mínimo del QUBO sigue siendo factible:</p>
                <table class="table table-sm mb-0">
                    <thead><tr><th>N</th><th>λ / λ<sub>def</sub></th><th class="text-end">λ</th><th class="text-end">E<sub>min</sub> QUBO</th><th>Mínimo factible</th><th class="text-end">Margen infactible</th></tr></thead>
                    <tbody>
                    <?php foreach ($e1 as $N => $b): foreach ($b['barrido_lambda_qubo'] as $l): ?>
                        <tr>
                            <td><?= (int) $N ?></td><td><?= $num($l['factor'], 2) ?></td><td class="text-end"><?= $num($l['lambda'], 1) ?></td>
                            <td class="text-end"><?= $num($l['E_min'], 1) ?></td>
                            <td><?= $l['minimo_factible'] ? '<span class="text-success">sí (C=' . $num($l['costo_minimo']) . ')</span>' : '<span class="text-danger">no</span>' ?></td>
                            <td class="text-end"><?= $num($l['brecha_espectral_infactible'], 1) ?></td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
                <p class="text-secondary mt-2 mb-0">«Margen infactible» = energía del mejor estado inválido − energía del mejor válido; si es negativo, el QUBO premia soluciones inválidas.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-activity text-primary"></i> QAOA-XY (p=2) con λ reducido</div>
            <div class="card-body small">
                <table class="table table-sm">
                    <thead><tr><th>N</th><th>λ / λ<sub>def</sub></th><th class="text-end">Costo</th><th class="text-end">Factible</th><th class="text-end">P(óptimo)</th></tr></thead>
                    <tbody>
                    <?php foreach ($e1 as $N => $b): foreach ($b['qaoa_lambda'] as $l): ?>
                        <tr><td><?= (int) $N ?></td><td><?= $num($l['factor'], 2) ?></td><td class="text-end"><?= $num($l['costo']) ?></td>
                            <td class="text-end"><?= $pct($l['fraccion_factible']) ?></td><td class="text-end"><?= $pct($l['prob_optimo'], 2) ?></td></tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
                <p class="text-secondary mb-0">Un λ menor suaviza el paisaje de energía pero puede dejar de garantizar que el mínimo sea factible: por eso se reporta siempre la fracción factible y el costo real.</p>
            </div>
        </div>
    </div>
</div>

<!-- Instancia completa -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-10-circle text-primary"></i> Instancia completa N = 10 (100 variables binarias)</div>
            <div class="card-body">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Método</th><th class="text-end">Corridas</th><th class="text-end">Mejor</th><th class="text-end">Media</th><th class="text-end">Gap mejor</th><th class="text-end">Tiempo medio</th></tr></thead>
                    <tbody>
                    <tr><td>Enumeración exacta (10! = 3 628 800)</td><td class="text-end">1</td><td class="text-end fw-bold"><?= $num($m['optimo_enumerado']) ?></td><td class="text-end">—</td><td class="text-end">0,0 %</td><td class="text-end"><?= $seg($m['tiempo_enumeracion_s']) ?></td></tr>
                    <?php
                    $filas = [['Recocido simulado (20 000 iter.)', $c['recocido']['runs']]];
                    $ls = [];
                    foreach ($c['busqueda_local']['runs'] as $run) { $ls[$run['arranques']][] = $run; }
                    foreach ($ls as $a => $runs) { $filas[] = ["Búsqueda local 2-swap, {$a} arranque(s)", $runs]; }
                    foreach ($c['lns_exacto'] as $k => $b) { $filas[] = ["LNS k={$k} · sub-solver exacto (clásico)", $b['runs']]; }
                    foreach ($c['lns_qaoa'] as $k => $b) { $filas[] = ["LNS k={$k} · sub-solver QAOA-XY (Qiskit, " . ($k * $k) . " qubits)", $b['runs'], true]; }
                    foreach ($filas as $fila):
                        [$nombre, $runs] = $fila; $cuantico = $fila[2] ?? false;
                        $cs = array_column($runs, 'costo');
                    ?>
                        <tr class="<?= $cuantico ? 'table-primary' : '' ?>">
                            <td><?= e($nombre) ?></td><td class="text-end"><?= count($runs) ?></td>
                            <td class="text-end fw-bold"><?= $num(min($cs)) ?></td><td class="text-end"><?= $num(array_sum($cs) / count($cs), 1) ?></td>
                            <td class="text-end"><?= $pct($gap(min($cs))) ?></td>
                            <td class="text-end"><?= $seg(array_sum(array_column($runs, 'tiempo_s')) / count($runs)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php foreach ($c['lns_qaoa'] as $k => $b):
                    $sp = array_sum(array_column($b['runs'], 'subproblemas'));
                    $so = array_sum(array_column($b['runs'], 'sub_optimo_alcanzado'));
                    $fm = array_sum(array_column($b['runs'], 'fraccion_factible_media')) / count($b['runs']); ?>
                    <p class="small mb-1"><strong>LNS + QAOA k=<?= (int) $k ?>:</strong> QAOA encontró el óptimo del subproblema en <?= $so ?> de <?= $sp ?> subproblemas
                        (<?= $pct($so / max($sp, 1), 0) ?>), con <?= $pct($fm, 0) ?> de muestras factibles en promedio.</p>
                <?php endforeach; ?>
                <p class="small text-secondary mb-1">Presupuesto: LNS exacto y LNS+QAOA k=3 hacen 60 iteraciones. LNS+QAOA k=4 hace solo <?= (int) ($c['lns_qaoa']['4']['runs'][0]['iteraciones'] ?? 0) ?> (p=1), por el costo de simular 16 qubits, así que no es directamente comparable.</p>
                <p class="small text-secondary mb-0">Gap = 100·(C − 340)/340, solo sobre asignaciones válidas. Todas las filas reportan costos reales C(x), no energías penalizadas.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-down text-primary"></i> Convergencia del LNS (costo por iteración)</div>
            <div class="card-body">
                <?php
                $series = [];
                foreach ($c['lns_exacto'] as $k => $b) { $series[] = ["exacto k={$k}", $b['runs'][0]['historial'], 'var(--text-light)']; }
                $colores = ['var(--brand-blue)', 'var(--brand-indigo)'];
                $ci = 0;
                foreach ($c['lns_qaoa'] as $k => $b) { $series[] = ["QAOA k={$k}", $b['runs'][0]['historial'], $colores[$ci++ % 2]]; }
                $maxIt = max(array_map(fn ($s) => count($s[1]), $series));
                $maxC = max(array_map(fn ($s) => max($s[1]), $series));
                $minC = $optimo;
                $W = 420; $H = 220; $L = 36; $B = 22;
                $sx = fn ($i) => $L + ($W - $L - 8) * $i / max($maxIt - 1, 1);
                $sy = fn ($v) => 8 + ($H - $B - 8) * (1 - ($v - $minC) / max($maxC - $minC, 1));
                ?>
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="w-100" role="img" aria-label="Convergencia LNS">
                    <line x1="<?= $L ?>" y1="<?= $sy($optimo) ?>" x2="<?= $W - 8 ?>" y2="<?= $sy($optimo) ?>" stroke="var(--accent-emerald)" stroke-dasharray="4 3"/>
                    <text x="<?= $W - 10 ?>" y="<?= $sy($optimo) - 4 ?>" text-anchor="end" font-size="10" fill="var(--accent-emerald)">C* = 340</text>
                    <text x="<?= $L - 4 ?>" y="12" text-anchor="end" font-size="10" fill="var(--text-soft)"><?= $num($maxC) ?></text>
                    <text x="<?= $L - 4 ?>" y="<?= $H - $B ?>" text-anchor="end" font-size="10" fill="var(--text-soft)"><?= $num($minC) ?></text>
                    <text x="<?= $W / 2 ?>" y="<?= $H - 4 ?>" text-anchor="middle" font-size="10" fill="var(--text-soft)">iteración (semilla 0)</text>
                    <?php foreach ($series as [$nombre, $hist, $color]):
                        $pts = [];
                        foreach ($hist as $i => $v) { $pts[] = round($sx($i), 1) . ',' . round($sy($v), 1); } ?>
                        <polyline points="<?= implode(' ', $pts) ?>" fill="none" stroke="<?= $color ?>" stroke-width="2"><title><?= e($nombre) ?></title></polyline>
                    <?php endforeach; ?>
                </svg>
                <div class="d-flex flex-wrap gap-3 small">
                    <?php foreach ($series as [$nombre, $hist, $color]): ?>
                        <span><span style="display:inline-block;width:12px;height:3px;background:<?= $color ?>;vertical-align:middle"></span> <?= e($nombre) ?> (final <?= $num(end($hist)) ?>)</span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Escalamiento -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-arrows-angle-expand text-primary"></i> Etapa 3 · Escalamiento</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr>
                    <th>N</th><th class="text-end">Variables QUBO</th><th class="text-end">Términos Ising</th><th class="text-end">Permutaciones</th>
                    <th class="text-end">Fracción factible de 2<sup>N²</sup></th><th class="text-end">Memoria statevector</th>
                    <th class="text-end">Puertas 2q (p=1, XY)</th><th class="text-end">Óptimo exacto</th><th class="text-end">Tiempo exacto</th><th class="text-end">Recocido</th>
                </tr></thead>
                <tbody>
                <?php foreach ($r['escalamiento'] as $row): ?>
                    <tr>
                        <td><?= (int) $row['N'] ?></td><td class="text-end"><?= (int) $row['variables_qubo'] ?></td>
                        <td class="text-end"><?= $num($row['terminos_ising']) ?></td><td class="text-end"><?= $num($row['permutaciones']) ?></td>
                        <td class="text-end"><?= sprintf('%.1e', $row['fraccion_factible_qubo']) ?></td>
                        <td class="text-end"><?= $bytes((float) $row['memoria_statevector_bytes']) ?></td>
                        <td class="text-end"><?= $num($row['circuito']['xy']['puertas_2q_p1']) ?></td>
                        <td class="text-end"><?= $num($row['optimo_exacto']) ?></td><td class="text-end"><?= $seg($row['tiempo_exacto_s']) ?></td>
                        <td class="text-end"><?= $num($row['recocido_costo']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-secondary mb-0">
            El QUBO completo pide 100 qubits y 2<sup>100</sup> amplitudes; la fracción de estados válidos cae a ~10<sup>−24</sup>. Por eso, para N=10, el
            componente cuántico trabaja solo sobre vecindarios de k elementos (k² qubits) dentro de un LNS clásico, en vez de simular un único circuito de 100 qubits.
        </p>
    </div>
</div>

<!-- Qué hace Qiskit -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-diagram-2 text-primary"></i> Qué hace Qiskit y qué es clásico</div>
            <div class="card-body small">
                <table class="table table-sm mb-0">
                    <tr><th style="width:38%">Qiskit (circuitos, simulados en Aer)</th><td>Hamiltoniano de Ising (<code>SparsePauliOp</code>, x = (1 − Z)/2); circuito QAOA con capas RZ/RZZ de costo y mezcladores RX o XY (<code>XXPlusYYGate</code>); estados W (<code>StatePreparation</code>); transpilación y muestreo con <code>SamplerV2</code> de Aer.</td></tr>
                    <tr><th>Clásico</th><td>Lectura del MPS y factorización F·D; armado del QUBO y λ; optimización de (γ, β) con COBYLA sobre el CVaR<sub>0,2</sub> de las energías muestreadas; decodificación y verificación de factibilidad; LNS (elegir vecindario, término lineal con los fijos, aceptar); todos los baselines.</td></tr>
                    <tr><th>No usado</th><td>Hardware cuántico real. Los tiempos son de simulación clásica y no anticipan tiempos en un QPU.</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightbulb text-primary"></i> Conclusiones</div>
            <div class="card-body small">
                <?php
                $xy3 = $agg($qaoaResumen['3|xy']['runs'], 'prob_optimo')['media'];
                $xy4f = $agg($qaoaResumen['4|xy']['runs'], 'fraccion_factible')['media'];
                $xy4o = $agg($qaoaResumen['4|xy']['runs'], 'prob_optimo')['media'];
                $x4f = $agg($qaoaResumen['4|x']['runs'], 'fraccion_factible')['media'];
                $azar3 = $e1['3']['azar']['uniforme_por_fila_W']; $azar4 = $e1['4']['azar']['uniforme_por_fila_W'];
                $lnsQ3 = $c['lns_qaoa']['3']['resumen'] ?? null; $lnsE3 = $c['lns_exacto']['3']['resumen'];
                ?>
                <ul class="mb-0">
                    <li>Con N=3 y N=4, el óptimo de control (24 y 32) aparece en las muestras de casi todas las configuraciones. La diferencia está en <strong>cuánta probabilidad</strong> pone QAOA en las buenas asignaciones.</li>
                    <li>El QAOA estándar con penalizaciones apenas supera al azar: con N=4 solo el <?= $pct($x4f, 1) ?> de las muestras es válido.
                        El mezclador XY, que respeta la estructura del problema, sube esa cifra al <?= $pct($xy4f, 0) ?>.</li>
                    <li>Hay que compararlo con el azar «justo» (un 1 por fila). Con N=3, QAOA-XY concentra P(óptimo) en <?= $pct($xy3, 0) ?> frente a <?= $pct($azar3['optima'], 1) ?> al azar.
                        Con N=4 duplica la factibilidad (<?= $pct($xy4f, 0) ?> vs <?= $pct($azar4['factible'], 1) ?>), pero P(óptimo) queda en el nivel del azar (<?= $pct($xy4o, 2) ?> vs <?= $pct($azar4['optima'], 2) ?>). Con p ≤ 3, el paisaje de N=4 ya es difícil para QAOA.</li>
                    <?php if ($lnsQ3): ?>
                    <li>Con N=10, el LNS con QAOA (k=3) obtiene <?= $num($lnsQ3['mejor']) ?> (media <?= $num($lnsQ3['media'], 1) ?>), frente a <?= $num($lnsE3['mejor']) ?> (media <?= $num($lnsE3['media'], 1) ?>) con sub-solver exacto.
                        Eso <strong>no</strong> es ventaja cuántica: QAOA resolvió el 100 % de los subproblemas, y la diferencia viene de cómo se rompen los empates entre sub-asignaciones igual de buenas.
                        Con k=3 hay solo 6 sub-asignaciones, que el muestreo encuentra casi seguro.</li>
                    <?php endif; ?>
                    <li>A esta escala, lo clásico es muy superior: la búsqueda local multiarranque y el recocido encuentran 340 en milisegundos o décimas de segundo, y la enumeración lo certifica en segundos.
                        QAOA es útil como componente de vecindario y para estudiar ansätze que respeten restricciones. Para que aporte de verdad haría falta k grande, donde k! ya no se puede enumerar, y eso hoy no se puede simular con statevector.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeEjecutar): ?>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-play-circle text-primary"></i> Ejecutar QAOA en vivo (simulador Aer)</div>
    <div class="card-body">
        <form id="form-qaoa" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-6 col-md-2"><label class="form-label small">N</label>
                <select name="n" class="form-select form-select-sm"><option>3</option><option>4</option></select></div>
            <div class="col-6 col-md-3"><label class="form-label small">Mezclador</label>
                <select name="mixer" class="form-select form-select-sm"><option value="xy">XY (conserva filas)</option><option value="x">Estándar RX</option></select></div>
            <div class="col-6 col-md-2"><label class="form-label small">Capas p</label>
                <select name="reps" class="form-select form-select-sm"><option>1</option><option selected>2</option><option>3</option></select></div>
            <div class="col-6 col-md-2"><label class="form-label small">Semilla</label>
                <input name="seed" type="number" value="7" min="0" class="form-control form-control-sm"></div>
            <div class="col-12 col-md-3"><button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-cpu"></i> Ejecutar</button></div>
        </form>
        <pre id="salida-qaoa" class="small mt-3 mb-0 p-2 bg-light border rounded d-none" style="white-space: pre-wrap;"></pre>
    </div>
</div>
<script>
document.getElementById('form-qaoa').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const out = document.getElementById('salida-qaoa');
    out.classList.remove('d-none');
    out.textContent = 'Ejecutando QAOA en Qiskit Aer…';
    try {
        const resp = await fetch('<?= base_url('/optimizacion-cuantica/ejecutar') ?>', { method: 'POST', body: new FormData(ev.target) });
        const j = await resp.json();
        if (j.error) { out.textContent = 'Error: ' + j.error; return; }
        out.textContent =
            `N=${j.n} · ${j.qubits} qubits · mezclador ${j.mixer} · p=${j.reps} · semilla ${j.semilla}\n` +
            `Mejor costo factible: ${j.costo ?? 'ninguno'} (óptimo exacto ${j.optimo_exacto}) · perm ${JSON.stringify(j.perm)}\n` +
            `Fracción factible: ${(100 * j.fraccion_factible).toFixed(1)} % · P(óptimo): ${(100 * j.prob_optimo).toFixed(2)} %\n` +
            `Profundidad ${j.profundidad} · puertas 2q ${j.puertas_2q} · ${j.evaluaciones} evaluaciones · ${j.tiempo_s.toFixed(2)} s`;
    } catch (e) { out.textContent = 'Error: ' + e; }
});
</script>
<?php endif; ?>

<div class="card mb-2">
    <div class="card-header"><i class="bi bi-arrow-repeat text-primary"></i> Reproducibilidad</div>
    <div class="card-body small">
        <p class="mb-2">Generado <?= e($r['generado']) ?> (modo <?= e($r['modo']) ?>, <?= $num($r['tiempo_total_s']) ?> s) ·
            Python <?= e($r['entorno']['python']) ?> · Qiskit <?= e($r['entorno']['qiskit']) ?> · Qiskit Aer <?= e($r['entorno']['qiskit_aer']) ?> ·
            NumPy <?= e($r['entorno']['numpy']) ?> · SciPy <?= e($r['entorno']['scipy']) ?></p>
        <pre class="bg-light border rounded p-2 mb-2">cd quantum
uv venv --python 3.12 .venv && uv pip install --python .venv/bin/python -r requirements.txt
.venv/bin/python run_experiments.py      # escribe results/results.json</pre>
        <p class="mb-0 text-secondary">Semillas: recocido 0–9, QAOA Etapa 1 {11, 23, 37}, LNS 0–4. Las semillas de los subproblemas QAOA del LNS se derivan del generador del LNS.
            Configuración y parámetros (γ, β) optimizados de cada corrida: en <code>quantum/results/results.json</code>.</p>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
