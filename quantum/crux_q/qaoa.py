"""QAOA en Qiskit para el QUBO del QAP.

Dos variantes:
  * "x"  : QAOA estándar. Estado inicial |+>^m, mezclador RX, ambas restricciones como penalización.
  * "xy" : QAOA con mezclador XY en anillo dentro de cada fila (elemento). Estado inicial = producto
           de estados W (cada elemento en superposición uniforme de ubicaciones); el mezclador conserva "un 1 por fila", así que solo la restricción
           de columnas queda como penalización (Hadfield et al., Quantum Alternating Operator Ansatz).

La función objetivo es el CVaR_α de la energía penalizada medida en muestras (Barkoutsos et al. 2020);
los parámetros se optimizan con COBYLA (clásico). Los circuitos se simulan con Qiskit Aer.
"""
from __future__ import annotations

import time
from dataclasses import dataclass, field

import numpy as np
from qiskit import QuantumCircuit, transpile
from qiskit.circuit import ParameterVector
from qiskit.circuit.library import StatePreparation, XXPlusYYGate
from qiskit.quantum_info import SparsePauliOp
from qiskit_aer import AerSimulator
from qiskit_aer.primitives import SamplerV2
from scipy.optimize import minimize

from .qubo import QUBO, build_qubo, decode, qubo_to_ising


@dataclass
class QAOAConfig:
    reps: int = 2
    mixer: str = "xy"            # "x" o "xy"
    shots: int = 2048
    final_shots: int = 8192
    cvar_alpha: float = 0.2
    maxiter: int = 120
    restarts: int = 2
    seed: int = 7
    lam: float | None = None     # None -> cota suficiente (qubo.default_lambda)


@dataclass
class QAOAResult:
    best_perm: list[int] | None
    best_cost: float | None
    feasible_fraction: float
    prob_optimal: float | None
    expected_energy: float
    num_qubits: int
    depth: int
    two_qubit_gates: int
    evaluations: int
    time_s: float
    params: list[float]
    config: dict
    lam: float
    top: list[dict] = field(default_factory=list)


def _cost_layer(qc: QuantumCircuit, ising: SparsePauliOp, gamma) -> None:
    for pauli, coeff in zip(ising.paulis, ising.coeffs):
        idx = [q for q, z in enumerate(pauli.z) if z]
        c = float(np.real(coeff))
        if len(idx) == 1:
            qc.rz(2 * c * gamma, idx[0])
        elif len(idx) == 2:
            qc.rzz(2 * c * gamma, idx[0], idx[1])


def build_circuit(ising: SparsePauliOp, n: int, reps: int, mixer: str, init_perm=None) -> QuantumCircuit:
    m = n * n
    gammas, betas = ParameterVector("γ", reps), ParameterVector("β", reps)
    qc = QuantumCircuit(m)
    if mixer == "x":
        qc.h(range(m))
    elif init_perm is not None:
        for i, j in enumerate(init_perm):            # arranque en caliente desde una asignación
            qc.x(i * n + j)
    else:
        w = np.zeros(2 ** n)                         # estado W por fila: superposición uniforme
        w[[1 << j for j in range(n)]] = 1 / np.sqrt(n)  # de "elemento i en cualquier ubicación"
        for i in range(n):
            qc.append(StatePreparation(w), [i * n + j for j in range(n)])
    for r in range(reps):
        _cost_layer(qc, ising, gammas[r])
        if mixer == "x":
            qc.rx(2 * betas[r], range(m))
        else:
            # anillo XY en cada fila: pares (j, j+1) pares e impares para paralelizar
            for parity in (0, 1):
                for i in range(n):
                    for j in range(parity, n if n > 2 else 1, 2):
                        a, b = i * n + j, i * n + (j + 1) % n
                        if a != b:
                            qc.append(XXPlusYYGate(2 * betas[r]), [a, b])
    qc.measure_all()
    return qc


def _bits_from_counts(counts: dict[str, int], m: int):
    keys = list(counts)
    bits = np.array([[int(ch) for ch in k[::-1]] for k in keys], dtype=np.int8)  # bit v = qubit v
    freqs = np.array([counts[k] for k in keys], dtype=float)
    return bits, freqs


def _energies(qubo: QUBO, bits: np.ndarray) -> np.ndarray:
    X = bits.astype(float)
    return np.einsum("si,ij,sj->s", X, qubo.Q, X) + qubo.const


def _cvar(energies: np.ndarray, freqs: np.ndarray, alpha: float) -> float:
    order = np.argsort(energies)
    e, w = energies[order], freqs[order] / freqs.sum()
    cum = np.cumsum(w)
    k = int(np.searchsorted(cum, alpha)) + 1
    ww = w[:k].copy()
    ww[-1] -= cum[k - 1] - alpha if cum[k - 1] > alpha else 0.0
    return float((e[:k] * ww).sum() / ww.sum())


def run_qaoa(F, D, cfg: QAOAConfig, B=None, init_perm=None, optimum: float | None = None,
             cost_fn=None) -> QAOAResult:
    """Ejecuta QAOA sobre el QUBO de (F, D, B). cost_fn(perm) da el costo real factible."""
    t0 = time.perf_counter()
    n = F.shape[0]
    m = n * n
    qubo = build_qubo(F, D, B, lam=cfg.lam, row_penalty=True)
    ising = qubo_to_ising(qubo)
    backend = AerSimulator(method="statevector", seed_simulator=cfg.seed)
    qc = transpile(build_circuit(ising, n, cfg.reps, cfg.mixer, init_perm), backend, optimization_level=1)
    sampler = SamplerV2(seed=cfg.seed)
    rng = np.random.default_rng(cfg.seed)
    evals = 0
    # escala de γ: la fase debe ser O(1) para el término de mayor peso
    gscale = 1.0 / max(np.abs(ising.coeffs[1:]).max().real, 1e-9)

    def sample(theta, shots):
        res = sampler.run([(qc, theta)], shots=shots).result()[0]
        return _bits_from_counts(res.data.meas.get_counts(), m)

    def objective(theta):
        nonlocal evals
        evals += 1
        bits, freqs = sample(theta, cfg.shots)
        return _cvar(_energies(qubo, bits), freqs, cfg.cvar_alpha)

    best_val, best_theta = np.inf, None
    for _ in range(cfg.restarts):
        x0 = np.concatenate([rng.uniform(0.1, 1.0, cfg.reps) * gscale,      # γ
                             rng.uniform(0.1, np.pi / 2, cfg.reps)])        # β
        res = minimize(objective, x0, method="COBYLA",
                       options={"maxiter": cfg.maxiter, "rhobeg": 0.3})
        if res.fun < best_val:
            best_val, best_theta = res.fun, res.x

    bits, freqs = sample(best_theta, cfg.final_shots)
    energies = _energies(qubo, bits)
    probs = freqs / freqs.sum()
    feas_mass, opt_mass = 0.0, 0.0
    best_perm, best_cost = None, None
    top = []
    for b, p, e in sorted(zip(bits, probs, energies), key=lambda t: -t[1]):
        perm = decode(b, n)
        c = None
        if perm is not None:
            c = cost_fn(perm) if cost_fn else e
            feas_mass += p
            if best_cost is None or c < best_cost:
                best_perm, best_cost = perm, c
            if optimum is not None and abs(c - optimum) < 1e-9:
                opt_mass += p
        if len(top) < 8:
            top.append({"bits": "".join(map(str, b)), "prob": float(p), "energia": float(e),
                        "perm": perm, "costo": c})
    ops = qc.count_ops()
    two_q = sum(v for k, v in ops.items() if k in ("rzz", "cx", "cz", "xx_plus_yy", "ecr", "swap"))
    return QAOAResult(
        best_perm=best_perm, best_cost=best_cost, feasible_fraction=float(feas_mass),
        prob_optimal=float(opt_mass) if optimum is not None else None,
        expected_energy=float((energies * probs).sum()), num_qubits=m, depth=qc.depth(),
        two_qubit_gates=int(two_q), evaluations=evals, time_s=time.perf_counter() - t0,
        params=[float(t) for t in best_theta], config=cfg.__dict__.copy(), lam=qubo.lam, top=top,
    )
