"""Método híbrido para la instancia completa: búsqueda en vecindarios grandes (LNS).

En cada iteración se eligen k elementos; los demás quedan fijos. El subproblema es un QAP
k x k sobre las ubicaciones que esos k elementos ocupan ahora, con un término lineal

    B[a, b] = sum_{r fuera del vecindario} F[s_a, r] * D[L_b, perm[r]]

que captura la interacción con los elementos fijos. Ese subproblema (k^2 qubits) se resuelve
con QAOA en Qiskit, o bien —para comparar de forma justa— por enumeración exacta (k!).
La parte clásica es: selección de vecindarios, armado del subproblema, aceptación y
reconstrucción de la asignación completa.
"""
from __future__ import annotations

import itertools
import time

import numpy as np

from .instance import QAPInstance
from .qaoa import QAOAConfig, run_qaoa


def subproblem(inst: QAPInstance, perm, S):
    S = list(S)
    L = [perm[s] for s in S]
    fixed = [r for r in range(inst.n) if r not in S]
    Fs = inst.F[np.ix_(S, S)]
    Ds = inst.D[np.ix_(L, L)]
    B = np.array([[sum(inst.F[s, r] * inst.D[l, perm[r]] for r in fixed) for l in L] for s in S])
    return Fs, Ds, B, L


def _sub_cost(Fs, Ds, B, sub_perm):
    k = len(sub_perm)
    c = sum(B[a, sub_perm[a]] for a in range(k))
    c += sum(Fs[a, b] * Ds[sub_perm[a], sub_perm[b]] for a in range(k) for b in range(a + 1, k))
    return float(c)


def _choose_neighborhood(inst: QAPInstance, rng, k: int):
    """k elementos al azar. (Probamos también vecindarios "conectados" por flujo: con
    sub-solver exacto, 60 iteraciones y 10 semillas daban peor costo medio: 375 vs 364 con k=4.)"""
    return [int(x) for x in rng.choice(inst.n, k, replace=False)]


def lns(inst: QAPInstance, k: int = 3, iters: int = 40, seed: int = 0, solver: str = "qaoa",
        qaoa_cfg: QAOAConfig | None = None, start=None, log=None) -> dict:
    t0 = time.perf_counter()
    rng = np.random.default_rng(seed)
    perm = list(rng.permutation(inst.n)) if start is None else list(start)
    perm = [int(x) for x in perm]
    cur = inst.cost(perm)
    history = [cur]
    stats = {"subproblemas": 0, "mejoras": 0, "sub_optimo_alcanzado": 0, "fraccion_factible_media": 0.0,
             "tiempo_cuantico_s": 0.0}
    feas = []
    for it in range(iters):
        S = _choose_neighborhood(inst, rng, k)
        Fs, Ds, B, L = subproblem(inst, perm, S)
        exact = min(itertools.permutations(range(k)), key=lambda p: _sub_cost(Fs, Ds, B, p))
        exact_cost = _sub_cost(Fs, Ds, B, exact)
        if solver == "qaoa":
            cfg = qaoa_cfg or QAOAConfig(reps=2, mixer="xy", maxiter=60, restarts=1)
            cfg = QAOAConfig(**{**cfg.__dict__, "seed": int(rng.integers(1 << 30))})
            r = run_qaoa(Fs, Ds, cfg, B=B, cost_fn=lambda p: _sub_cost(Fs, Ds, B, p))
            stats["tiempo_cuantico_s"] += r.time_s
            feas.append(r.feasible_fraction)
            sub = r.best_perm
        else:
            sub = list(exact)
        stats["subproblemas"] += 1
        if sub is not None:
            if abs(_sub_cost(Fs, Ds, B, sub) - exact_cost) < 1e-9:
                stats["sub_optimo_alcanzado"] += 1
            cand = perm[:]
            for a, s in enumerate(S):
                cand[s] = L[sub[a]]
            c = inst.cost(cand)
            if c < cur - 1e-9:
                stats["mejoras"] += 1
            if c <= cur + 1e-9:          # se aceptan movimientos laterales para salir de mesetas
                perm, cur = cand, c
        history.append(cur)
        if log:
            log(f"  LNS[{solver}] it={it + 1:3d} costo={cur:.0f}")
    stats["fraccion_factible_media"] = float(np.mean(feas)) if feas else None
    return {"metodo": f"LNS k={k} + {'QAOA-XY (Qiskit)' if solver == 'qaoa' else 'sub-solver exacto'}",
            "costo": cur, "perm": perm, "historial": history, "semilla": seed, "k": k,
            "qubits_por_subproblema": k * k, "iteraciones": iters,
            "tiempo_s": time.perf_counter() - t0, **stats}
