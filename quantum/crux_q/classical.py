"""Baselines clásicos: enumeración exacta, búsqueda local 2-swap y recocido simulado."""
from __future__ import annotations

import itertools
import math
import time

import numpy as np

from .instance import QAPInstance


def brute_force(inst: QAPInstance, chunk: int = 200_000) -> dict:
    """Enumera las n! permutaciones (vectorizado por bloques). Exacto; práctico hasta n=10."""
    t0 = time.perf_counter()
    n = inst.n
    iu, ku = np.triu_indices(n, 1)
    f = inst.F[iu, ku]
    best_cost, best_perm, evaluated = math.inf, None, 0
    it = itertools.permutations(range(n))
    while True:
        block = np.array(list(itertools.islice(it, chunk)), dtype=np.int8)
        if block.size == 0:
            break
        costs = (inst.D[block[:, iu], block[:, ku]] * f).sum(axis=1)
        idx = int(np.argmin(costs))
        evaluated += len(block)
        if costs[idx] < best_cost:
            best_cost, best_perm = float(costs[idx]), block[idx].tolist()
    return {"metodo": "Enumeración exacta", "costo": best_cost, "perm": best_perm,
            "evaluadas": evaluated, "tiempo_s": time.perf_counter() - t0}


def _swap_delta(F, D, p, r, s) -> float:
    """Cambio de costo al intercambiar las ubicaciones de los elementos r y s (F, D simétricas)."""
    n = len(p)
    pr, ps = p[r], p[s]
    d = 0.0
    for k in range(n):
        if k == r or k == s:
            continue
        pk = p[k]
        d += (F[r, k] - F[s, k]) * (D[ps, pk] - D[pr, pk])
    return d


def local_search(inst: QAPInstance, perm, F=None, D=None) -> list[int]:
    """Descenso por intercambios (2-swap, primera mejora) hasta óptimo local."""
    F = inst.F if F is None else F
    D = inst.D if D is None else D
    p = list(perm)
    n = len(p)
    improved = True
    while improved:
        improved = False
        for r in range(n - 1):
            for s in range(r + 1, n):
                if _swap_delta(F, D, p, r, s) < -1e-12:
                    p[r], p[s] = p[s], p[r]
                    improved = True
    return p


def simulated_annealing(inst: QAPInstance, seed: int = 0, iters: int = 20_000,
                        t0: float | None = None, t_end: float = 0.05) -> dict:
    """Recocido simulado sobre permutaciones con movimiento 2-swap."""
    start = time.perf_counter()
    rng = np.random.default_rng(seed)
    n = inst.n
    p = rng.permutation(n).tolist()
    cur = inst.cost(p)
    best, best_p = cur, p[:]
    t0 = t0 if t0 is not None else max(1.0, float(inst.F.max() * inst.D.max()))
    alpha = (t_end / t0) ** (1.0 / max(iters, 1))
    T = t0
    for _ in range(iters):
        r, s = rng.choice(n, 2, replace=False)
        delta = _swap_delta(inst.F, inst.D, p, r, s)
        if delta <= 0 or rng.random() < math.exp(-delta / T):
            p[r], p[s] = p[s], p[r]
            cur += delta
            if cur < best - 1e-12:
                best, best_p = cur, p[:]
        T *= alpha
    return {"metodo": "Recocido simulado", "costo": inst.cost(best_p), "perm": best_p,
            "iteraciones": iters, "semilla": seed, "tiempo_s": time.perf_counter() - start}


def multistart_local_search(inst: QAPInstance, starts: int = 50, seed: int = 0) -> dict:
    start = time.perf_counter()
    rng = np.random.default_rng(seed)
    best, best_p = math.inf, None
    for _ in range(starts):
        p = local_search(inst, rng.permutation(inst.n).tolist())
        c = inst.cost(p)
        if c < best:
            best, best_p = c, p
    return {"metodo": "Búsqueda local 2-swap multiarranque", "costo": best, "perm": best_p,
            "arranques": starts, "semilla": seed, "tiempo_s": time.perf_counter() - start}
