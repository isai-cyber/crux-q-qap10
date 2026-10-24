<?php

declare(strict_types=1);

/**
 * Optimización cuántica — reto QAP10 (NTT DATA · Qiskit Fall Fest Lima).
 *
 * Muestra los resultados generados por el motor Python/Qiskit de `quantum/`
 * (quantum/run_experiments.py -> quantum/results/results.json). La página es
 * pública para que el jurado pueda revisarla sin cuenta; la ejecución en vivo de
 * QAOA (que lanza un proceso Python) queda restringida a administradores.
 */
final class OptimizacionController
{
    private const QUANTUM_DIR = __DIR__ . '/../../quantum';

    public function index(): void
    {
        $dir = self::QUANTUM_DIR . '/results';
        $archivo = is_file("{$dir}/results.json") ? "{$dir}/results.json" : "{$dir}/results_quick.json";
        $r = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;
        $puedeEjecutar = Auth::check() && (Auth::user()['rol'] ?? '') === 'admin';

        require dirname(__DIR__, 2) . '/views/modulos/optimizacion/index.php';
    }

    public function ejecutar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || (Auth::user()['rol'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Solo administradores pueden ejecutar QAOA en vivo.']);
            return;
        }
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Token CSRF inválido.']);
            return;
        }

        $n = in_array((int) ($_POST['n'] ?? 3), [3, 4], true) ? (int) $_POST['n'] : 3;
        $mixer = ($_POST['mixer'] ?? 'xy') === 'x' ? 'x' : 'xy';
        $reps = max(1, min(3, (int) ($_POST['reps'] ?? 2)));
        $seed = max(0, min(1_000_000, (int) ($_POST['seed'] ?? 7)));

        $python = self::QUANTUM_DIR . '/.venv/bin/python';
        if (!is_file($python)) {
            http_response_code(500);
            echo json_encode(['error' => 'No se encontró quantum/.venv. Ver quantum/README.md.']);
            return;
        }
        $cmd = sprintf(
            'cd %s && %s cli.py --n %d --mixer %s --reps %d --seed %d 2>&1',
            escapeshellarg(self::QUANTUM_DIR), escapeshellarg($python), $n, $mixer, $reps, $seed
        );
        $salida = shell_exec($cmd);
        $json = is_string($salida) ? json_decode(trim((string) strrchr("\n" . trim($salida), "\n")), true) : null;
        if (!is_array($json)) {
            log_error('QAOA en vivo falló: ' . (string) $salida);
            http_response_code(500);
            echo json_encode(['error' => 'La ejecución de QAOA falló. Revisa el log.']);
            return;
        }
        echo json_encode($json);
    }
}
