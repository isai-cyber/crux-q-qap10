# Resultados — Reto QAP10

Generado: 2026-10-24T03:15:27+00:00 · modo completo · Python 3.12.13 · Qiskit 2.6.1 · Qiskit Aer 0.18.0 · NumPy 2.5.4 · SciPy 1.18.1

Todos los circuitos se simularon en CPU con Qiskit Aer. Los costos reportados son costos reales C(x) de asignaciones válidas; cuando aparece una energía del QUBO, se indica explícitamente.

## Modelo y datos

* Variables leídas: 100 x_ij y 4050 y_ijkl. Error de la factorización F·D: 0.0.
* Óptimo por enumeración de 10!: **340** en 4.24 s. Con la suma directa del MPS en la misma permutación da 340.
* perm* = [2, 6, 3, 0, 8, 4, 5, 1, 7, 9] (elemento i → ubicación perm[i], índices desde 0).
* N=3: óptimo calculado 24, control 24 → OK.
* N=4: óptimo calculado 32, control 32 → OK.

## Etapa 1 · N = 3 y N = 4

| N | Método | Qubits | Mejor costo factible | Fracción factible (media) | P(óptimo) (media) | Puertas 2q | Tiempo medio |
|---|---|---|---|---|---|---|---|
| 3 | Enumeración exacta | — | 24 | 100 % | — | — | 0.2 ms |
| 3 | Recocido simulado (5 semillas) | — | 24 (media 24.0) | 100 % | — | — | 0.03 s |
| 3 | QAOA estándar p=1 (3 semillas) | 9 | 24 | 5.6 % | 2.10 % | 36 | 0.80 s |
| 3 | QAOA estándar p=2 (3 semillas) | 9 | 24 | 4.2 % | 1.37 % | 72 | 1.60 s |
| 3 | QAOA estándar p=3 (3 semillas) | 9 | 24 | 4.3 % | 1.39 % | 108 | 2.33 s |
| 3 | QAOA XY p=1 (3 semillas) | 9 | 24 | 39.9 % | 17.43 % | 48 | 1.11 s |
| 3 | QAOA XY p=2 (3 semillas) | 9 | 24 | 43.6 % | 23.44 % | 84 | 1.37 s |
| 3 | QAOA XY p=3 (3 semillas) | 9 | 24 | 35.7 % | 21.07 % | 120 | 3.31 s |
| 3 | *Azar: bits uniformes / un 1 por fila* | | | 1.17 % / 22.2 % | 0.391 % / 7.41 % | | |
| 4 | Enumeración exacta | — | 32 | 100 % | — | — | 0.1 ms |
| 4 | Recocido simulado (5 semillas) | — | 32 (media 32.0) | 100 % | — | — | 0.04 s |
| 4 | QAOA estándar p=1 (3 semillas) | 16 | 32 | 1.0 % | 0.10 % | 96 | 4.46 s |
| 4 | QAOA estándar p=2 (3 semillas) | 16 | 38 | 0.1 % | 0.00 % | 192 | 9.66 s |
| 4 | QAOA estándar p=3 (3 semillas) | 16 | 32 | 0.2 % | 0.04 % | 288 | 20.64 s |
| 4 | QAOA XY p=1 (3 semillas) | 16 | 32 | 19.7 % | 0.50 % | 140 | 6.43 s |
| 4 | QAOA XY p=2 (3 semillas) | 16 | 32 | 18.8 % | 0.82 % | 236 | 12.35 s |
| 4 | QAOA XY p=3 (3 semillas) | 16 | 32 | 18.5 % | 1.33 % | 332 | 27.99 s |
| 4 | *Azar: bits uniformes / un 1 por fila* | | | 0.04 % / 9.4 % | 0.003 % / 0.78 % | | |

### λ

| N | λ/λ_def | λ | E_min QUBO | ¿Mínimo factible? | Margen (mejor inválido − mejor válido) |
|---|---|---|---|---|---|
| 3 | 0.1 | 3.3 | 10.6 | **no** | -13.4 |
| 3 | 0.25 | 8.2 | 20.5 | **no** | -3.5 |
| 3 | 0.5 | 16.5 | 24.0 | sí | 13.0 |
| 3 | 1.0 | 33.0 | 24.0 | sí | 46.0 |
| 3 | 2.0 | 66.0 | 24.0 | sí | 112.0 |
| 4 | 0.1 | 6.7 | 19.4 | **no** | -12.6 |
| 4 | 0.25 | 16.8 | 32.0 | sí | 7.5 |
| 4 | 0.5 | 33.5 | 32.0 | sí | 41.0 |
| 4 | 1.0 | 67.0 | 32.0 | sí | 108.0 |
| 4 | 2.0 | 134.0 | 32.0 | sí | 242.0 |

## Instancia completa N = 10

| Método | Corridas | Mejor | Media | Gap (mejor) | Tiempo medio |
|---|---|---|---|---|---|
| Enumeración exacta | 1 | 340 | — | 0.0 % | 4.24 s |
| Recocido simulado (20 000 iter.) | 10 | 340 | 349.0 | 0.0 % | 0.50 s |
| Búsqueda local 2-swap, 1 arranque(s) | 10 | 364 | 374.2 | 7.1 % | 1.2 ms |
| Búsqueda local 2-swap, 5 arranque(s) | 10 | 358 | 366.2 | 5.3 % | 5.6 ms |
| Búsqueda local 2-swap, 20 arranque(s) | 10 | 340 | 351.6 | 0.0 % | 0.02 s |
| LNS k=3, sub-solver exacto | 5 | 352 | 370.8 | 3.5 % | 6.2 ms |
| LNS k=4, sub-solver exacto | 5 | 362 | 366.0 | 6.5 % | 0.01 s |
| **LNS k=3, sub-solver QAOA-XY (9 qubits)** | 5 | 350 | 366.0 | 2.9 % | 80.84 s |
| **LNS k=4, sub-solver QAOA-XY (16 qubits)** | 2 | 368 | 373.0 | 8.2 % | 134.31 s |

* LNS+QAOA k=3: QAOA halló el óptimo del subproblema en 300/300 casos (100 %); fracción factible media 32 %; config {"reps": 2, "mixer": "xy", "shots": 1024, "maxiter": 60, "cvar_alpha": 0.2}.
* LNS+QAOA k=4: QAOA halló el óptimo del subproblema en 60/60 casos (100 %); fracción factible media 14 %; config {"reps": 1, "mixer": "xy", "shots": 1024, "maxiter": 40, "cvar_alpha": 0.2}.

## Etapa 3 · Escalamiento

| N | Variables QUBO | Términos Ising | Permutaciones | Fracción factible de 2^(N²) | Memoria statevector | Puertas 2q (p=1, XY) | Óptimo exacto | Tiempo exacto |
|---|---|---|---|---|---|---|---|---|
| 3 | 9 | 46 | 6 | 1.2e-02 | 8 KiB | 90 | 24.0 | 0.1 ms |
| 4 | 16 | 113 | 24 | 3.7e-04 | 1 MiB | 224 | 32.0 | 0.1 ms |
| 5 | 25 | 266 | 120 | 3.6e-06 | 512 MiB | 530 | 58.0 | 0.2 ms |
| 6 | 36 | 517 | 720 | 1.0e-08 | 1 TiB | 1032 | 94.0 | 0.7 ms |
| 7 | 49 | 890 | 5040 | 9.0e-12 | 8 PiB | 1778 | 112.0 | 9.0 ms |
| 8 | 64 | 1521 | 40320 | 2.2e-15 | 256 EiB | 3040 | 214.0 | 0.06 s |
| 9 | 81 | 2386 | 362880 | 1.5e-19 | 3.36e+07 EiB | 4770 | 264.0 | 0.79 s |
| 10 | 100 | 3611 | 3628800 | 2.9e-24 | 1.76e+13 EiB | 7220 | 340.0 | 7.10 s |

## Conclusiones

1. **Datos**: la reconstrucción de F y D es exacta (rango 1). Reproduce C* = 340 y los controles 24 y 32 con pares contados una vez.
2. **QAOA estándar con penalizaciones**: con N=4 solo el 0.5 % de las muestras es factible (azar uniforme: 0.04 %). La penalización domina el paisaje y QAOA de baja profundidad no la supera.
3. **QAOA-XY** (respeta «un elemento → una ubicación»): factibilidad 40 % (N=3) y 19 % (N=4). Contra el azar justo (un 1 por fila), concentra P(óptimo) en N=3 (21 % vs 7.4 %), pero no en N=4 (0.88 % vs 0.78 %).
4. **Híbrido N=10**: LNS+QAOA k=3 logra 350 (media 366.0, gap 2.9 %), frente a 352 (media 370.8) con sub-solver exacto. QAOA resolvió el 100 % de los subproblemas; la diferencia viene de cómo se rompen los empates, no de una ventaja cuántica. Con k=3 hay solo 6 sub-asignaciones.
5. **Clásico**: el recocido y la búsqueda local multiarranque alcanzan 340 en ≤ 0,5 s, y la enumeración lo certifica en segundos. Para N=10 no hay evidencia de ventaja cuántica. El valor de este trabajo está en el diagnóstico: qué codificación y qué mezclador hacen viable a QAOA, y por qué el QUBO completo (100 qubits, fracción factible ~1e-24) no es abordable directamente.
