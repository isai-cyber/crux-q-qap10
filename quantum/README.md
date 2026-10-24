# Crux Q — Reto QAP10 (NTT DATA · Qiskit Fall Fest Lima)

Motor de optimización clásica, cuántica (Qiskit) e híbrida para la instancia MIPLIB `qap10`.
Los resultados se muestran en Crux A.I, módulo **Optimización cuántica** (`/optimizacion-cuantica`).

## Instalación y ejecución

```bash
cd quantum
uv venv --python 3.12 .venv
uv pip install --python .venv/bin/python -r requirements.txt
.venv/bin/python run_experiments.py           # completo -> results/results.json (~20-30 min)
.venv/bin/python run_experiments.py --quick   # corto    -> results/results_quick.json (~3 min)
.venv/bin/python cli.py --n 4 --mixer xy --reps 2 --seed 7   # una corrida QAOA (JSON)
```

`data/qap10.mps.gz` se descargó de <https://miplib.zib.de/instance_details_qap10.html>.

## Estructura

| Archivo | Qué hace |
|---|---|
| `crux_q/instance.py` | Lee el MPS y reconstruye F y D; incluye las verificaciones de escala |
| `crux_q/classical.py` | Enumeración exacta (vectorizada), búsqueda local 2-swap y recocido simulado |
| `crux_q/qubo.py` | QUBO con penalizaciones one-hot, cota para λ, Ising `SparsePauliOp` y decodificación |
| `crux_q/qaoa.py` | Circuitos QAOA (mezclador RX o XY), CVaR + COBYLA y muestreo con Qiskit Aer |
| `crux_q/hybrid.py` | LNS: subproblemas de k elementos resueltos con QAOA o por enumeración exacta |
| `run_experiments.py` | Etapa 1, instancia completa y escalamiento; escribe `results/*.json` |
| `cli.py` | Una ejecución QAOA, usada por el botón "Ejecutar en vivo" de Crux |

## 1. Modelo y lectura de datos

El MPS es una linealización tipo Adams-Johnson con 100 variables `X` (x_ij), 4 050 variables `Y`
(y = x_ij·x_kl, con i<k y j≠l), 20 filas de asignación `NX` y 1 800 filas de enlace `C..A..`.

1. Fila `NX01..NX10` de cada `X` → elemento i; fila `NX11..NX20` → ubicación j.
2. Cada fila de enlace contiene exactamente una `X` (coeficiente −1). Al seguir las 4 filas de cada `Y`
   se llega siempre a dos `X` distintas, lo que da (i, j, k, l).
3. El coeficiente en `NOBJ` es c_ijkl. La matriz c[(i,k),(j,l)] de 45 × 90 es de **rango 1**
   (segundo valor singular ≈ 1e−13), así que c_ijkl = F_ik·D_jl. La escala se fija con min(D>0) = 1.

Verificaciones (todas pasan; ver `results.json → modelo`):

* error de la factorización = 0;
* para la permutación óptima, C con F·D = suma directa de coeficientes del MPS = **340**;
* la enumeración de las 10! permutaciones da **340** = C*;
* las subinstancias N=3 y N=4 dan **24** y **32** (óptimos de control);
* D es simétrica y coincide con la distancia Manhattan en una rejilla de 4 + 4 + 2 posiciones.

Costo: `C(p) = Σ_{i<k} F_ik · D_{p(i) p(k)}`, donde cada par se cuenta una vez, igual que en el archivo.

## 2. Componente Qiskit

* **QUBO** con `H = C(x) + λ Σ_i(Σ_j x_ij − 1)² + λ Σ_j(Σ_i x_ij − 1)²`.
  El λ por defecto es `max_i Σ_k F_ik · max D + 1`: es una cota suficiente, porque ninguna violación de una
  restricción one-hot ahorra más que eso. El barrido de λ (enumerando los 2^(N²) estados) muestra con qué valores
  el mínimo del QUBO deja de ser factible.
* **Ising**: con `x = (1 − Z)/2` se obtiene un `SparsePauliOp`. Comprobamos que su diagonal coincide con las
  energías del QUBO.
* **QAOA estándar**: estado inicial |+⟩, capas de costo RZ/RZZ y mezclador RX.
* **QAOA-XY** (Quantum Alternating Operator Ansatz): cada fila parte de un estado W y usa un mezclador
  `XXPlusYYGate` en anillo, que conserva "un elemento → una ubicación". Así solo la restricción de
  columnas queda como penalización.
* **Optimización**: COBYLA (clásico) sobre el CVaR_0.2 de la energía muestreada con `SamplerV2` de Aer.
  Luego se toman 8 192 disparos finales y se reportan la fracción factible, P(óptimo) y el mejor **costo real** factible.
* **Híbrido N=10 (LNS)**: en cada iteración se eligen k elementos al azar y el subproblema k×k
  (k² qubits, con un término lineal que representa a los elementos fijos) se resuelve con QAOA-XY. Como control
  justo, el mismo LNS se corre con un sub-solver exacto.

Todo circuito se **simula** en CPU (Qiskit Aer). No se usó hardware cuántico.

## 3. Resultados

Ver el módulo web o `results/results.json`. El resumen y las conclusiones están en `RESULTADOS.md`.
