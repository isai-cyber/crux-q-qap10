"""Formulación QUBO con penalizaciones y su Hamiltoniano de Ising (x = (1 - Z) / 2).

Variable binaria v = i * n + j  <->  x_ij = 1 si el elemento i va a la ubicación j.
Se admite un término lineal B_ij para los subproblemas del método híbrido (interacción
con los elementos que quedan fijos fuera del vecindario).
"""
from __future__ import annotations

from dataclasses import dataclass

import numpy as np
from qiskit.quantum_info import SparsePauliOp


@dataclass
class QUBO:
    n: int
    Q: np.ndarray        # matriz triangular superior (incluye diagonal = términos lineales)
    const: float
    lam: float

    @property
    def num_vars(self) -> int:
        return self.n * self.n

    def energy(self, x: np.ndarray) -> float:
        return float(x @ self.Q @ x + self.const)

    def energies_all(self) -> np.ndarray:
        """Energía penalizada de los 2^(n^2) estados (solo para n <= 4). Índice = entero
        cuyo bit v es x_v (mismo orden que los bitstrings de Qiskit)."""
        m = self.num_vars
        idx = np.arange(2 ** m, dtype=np.uint32)
        X = ((idx[:, None] >> np.arange(m, dtype=np.uint32)) & 1).astype(np.float64)
        return np.einsum("si,ij,sj->s", X, self.Q, X) + self.const


def assignment_cost_terms(F, D, B=None):
    """Coeficientes del costo C(x) = sum_{i<k, j!=l} F_ik D_jl x_ij x_kl + sum B_ij x_ij."""
    n = F.shape[0]
    m = n * n
    Q = np.zeros((m, m))
    for i in range(n):
        for k in range(i + 1, n):
            if F[i, k] == 0:
                continue
            for j in range(n):
                for l in range(n):
                    if j != l and D[j, l] != 0:
                        a, b = i * n + j, k * n + l
                        Q[min(a, b), max(a, b)] += F[i, k] * D[j, l]
    if B is not None:
        Q[np.diag_indices(m)] += np.asarray(B).reshape(-1)
    return Q


def default_lambda(F, D, B=None) -> float:
    """Cota suficiente para que el mínimo del QUBO sea factible.

    Quitar o mover una asignación x_ij cambia C(x) a lo sumo en
    max_i sum_k F_ik * max(D) + max|B|; un λ mayor que eso hace que cualquier violación
    de una restricción one-hot cueste más de lo que ahorra."""
    bound = float(F.sum(axis=1).max() * D.max())
    if B is not None:
        bound += float(np.abs(B).max())
    return bound + 1.0


def build_qubo(F, D, B=None, lam: float | None = None, row_penalty: bool = True) -> QUBO:
    n = F.shape[0]
    lam = default_lambda(F, D, B) if lam is None else float(lam)
    Q = assignment_cost_terms(F, D, B)
    const = 0.0
    groups = []
    if row_penalty:
        groups += [[i * n + j for j in range(n)] for i in range(n)]   # cada elemento, una ubicación
    groups += [[i * n + j for i in range(n)] for j in range(n)]       # cada ubicación, un elemento
    for g in groups:
        # λ (sum x - 1)^2 = λ [ -sum x + 2 sum_{a<b} x_a x_b + 1 ]   (x^2 = x)
        for a_idx, a in enumerate(g):
            Q[a, a] -= lam
            for b in g[a_idx + 1:]:
                Q[min(a, b), max(a, b)] += 2 * lam
        const += lam
    return QUBO(n, Q, const, lam)


def qubo_to_ising(qubo: QUBO) -> SparsePauliOp:
    """H = sum_v h_v Z_v + sum_{u<v} J_uv Z_u Z_v + c, sustituyendo x_v = (1 - Z_v)/2.

    Convención de Qiskit: el carácter más a la derecha de la etiqueta Pauli es el qubit 0."""
    m = qubo.num_vars
    h = np.zeros(m)
    terms: dict[tuple[int, int], float] = {}
    c = qubo.const
    for u in range(m):
        quv = qubo.Q[u, u]
        if quv:
            c += quv / 2
            h[u] -= quv / 2
        for v in range(u + 1, m):
            q = qubo.Q[u, v]
            if q:
                c += q / 4
                h[u] -= q / 4
                h[v] -= q / 4
                terms[(u, v)] = terms.get((u, v), 0.0) + q / 4
    paulis = [("", [], c)]
    paulis += [("Z", [u], h[u]) for u in range(m) if abs(h[u]) > 1e-12]
    paulis += [("ZZ", [u, v], J) for (u, v), J in terms.items() if abs(J) > 1e-12]
    return SparsePauliOp.from_sparse_list(paulis, num_qubits=m).simplify()


def decode(bits: np.ndarray, n: int):
    """Bits (x_v) -> permutación si la matriz es de permutación; si no, None."""
    X = bits.reshape(n, n)
    if (X.sum(axis=0) == 1).all() and (X.sum(axis=1) == 1).all():
        return [int(np.argmax(row)) for row in X]
    return None
