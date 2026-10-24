"""Lectura de qap10.mps.gz y reconstrucción del modelo cuadrático (F, D).

El MPS de MIPLIB es una linealización tipo Adams-Johnson del QAP:
  * X001..X100  -> x_ij  (fila NX01..NX10 = elemento i, fila NX11..NX20 = ubicación j)
  * Y...        -> y_ijkl = x_ij * x_kl, con i < k y j != l (4 050 variables)
  * filas C..A..: restricciones de enlace sum(y) = x (cada una contiene exactamente una X con -1)
  * NOBJ        -> coeficiente c_ijkl de cada y

Para recuperar (i, j, k, l) de cada Y se siguen sus 4 filas de enlace hasta las X que
contienen: siempre son exactamente dos X distintas. La matriz c[(i,k), (j,l)] (45 x 90)
resulta de rango 1, así que c_ijkl = F_ik * D_jl. La escala se fija con min(D>0) = 1;
el costo C(x) no depende de esa elección y se verifica contra el óptimo C* = 340.
"""
from __future__ import annotations

import gzip
from collections import defaultdict
from dataclasses import dataclass
from pathlib import Path

import numpy as np

DATA_DIR = Path(__file__).resolve().parent.parent / "data"
DEFAULT_MPS = DATA_DIR / "qap10.mps.gz"
OPTIMUM_FULL = 340
CONTROL_OPTIMA = {3: 24, 4: 32}


@dataclass
class QAPInstance:
    F: np.ndarray  # flujo entre elementos (simétrica, diagonal 0)
    D: np.ndarray  # distancia entre ubicaciones (simétrica, diagonal 0)
    name: str = "qap10"

    @property
    def n(self) -> int:
        return self.F.shape[0]

    def sub(self, n: int) -> "QAPInstance":
        """Subinstancia con las primeras n filas y columnas (Etapa 1)."""
        return QAPInstance(self.F[:n, :n].copy(), self.D[:n, :n].copy(), f"{self.name}[N={n}]")

    def cost(self, perm) -> float:
        """C(perm) = sum_{i<k} F_ik D_{perm[i] perm[k]}; perm[i] = ubicación del elemento i.

        Cada par se cuenta una sola vez (convención del archivo MPS)."""
        p = np.asarray(perm)
        return float(np.triu(self.F * self.D[np.ix_(p, p)], 1).sum())


def _read_columns(path: Path) -> dict[str, dict[str, float]]:
    cols: dict[str, dict[str, float]] = defaultdict(dict)
    section = None
    with gzip.open(path, "rt") as fh:
        for line in fh:
            if not line.startswith(" "):
                section = line.split()[0]
                continue
            if section != "COLUMNS" or "MARKER" in line:
                continue
            tok = line.split()
            for row, val in zip(tok[1::2], tok[2::2]):
                cols[tok[0]][row] = float(val)
    return cols


def reconstruct_coefficients(path: Path = DEFAULT_MPS) -> tuple[np.ndarray, int]:
    """Devuelve el tensor c[i, j, k, l] (solo i<k, j!=l) y el número de variables Y leídas."""
    cols = _read_columns(path)
    xvars = {c: v for c, v in cols.items() if c.startswith("X")}
    n = int(round(len(xvars) ** 0.5))

    x_index: dict[str, tuple[int, int]] = {}
    link_row_owner: dict[str, str] = {}
    for name, entries in xvars.items():
        nx = sorted(int(r[2:]) for r in entries if r.startswith("NX"))
        x_index[name] = (nx[0] - 1, nx[1] - n - 1)
        for row, val in entries.items():
            if row.startswith("C"):
                assert row not in link_row_owner, f"fila {row} con más de una X"
                link_row_owner[row] = name

    coef = np.zeros((n, n, n, n))
    n_y = 0
    for name, entries in cols.items():
        if not name.startswith("Y"):
            continue
        xs = {link_row_owner[r] for r in entries if r.startswith("C")}
        assert len(xs) == 2, f"{name} enlaza {len(xs)} X"
        (i, j), (k, l) = sorted(x_index[x] for x in xs)
        assert i < k and j != l
        coef[i, j, k, l] = entries.get("NOBJ", 0.0)
        n_y += 1
    return coef, n_y


def factor_flow_distance(coef: np.ndarray) -> tuple[np.ndarray, np.ndarray, float]:
    """Factoriza c_ijkl = F_ik D_jl. Devuelve F, D y el error relativo de la factorización."""
    n = coef.shape[0]
    pairs_ik = [(i, k) for i in range(n) for k in range(i + 1, n)]
    pairs_jl = [(j, l) for j in range(n) for l in range(n) if j != l]
    M = np.array([[coef[i, j, k, l] for (j, l) in pairs_jl] for (i, k) in pairs_ik])

    # Rango 1: tomamos la fila (i,k) con mayor norma como perfil de distancias.
    ref = int(np.argmax(np.abs(M).sum(axis=1)))
    d_profile = M[ref]
    d_unit = d_profile / d_profile[d_profile > 0].min()  # escala: min(D>0) = 1
    f_vals = M @ d_unit / (d_unit @ d_unit)

    D = np.zeros((n, n))
    for (j, l), v in zip(pairs_jl, d_unit):
        D[j, l] = v
    F = np.zeros((n, n))
    for (i, k), v in zip(pairs_ik, f_vals):
        F[i, k] = F[k, i] = v
    F, D = np.round(F, 9), np.round(D, 9)
    rel_err = float(np.abs(np.outer(f_vals, d_unit) - M).max() / max(np.abs(M).max(), 1e-12))
    return F, D, rel_err


def load_qap10(path: Path = DEFAULT_MPS) -> tuple[QAPInstance, dict]:
    coef, n_y = reconstruct_coefficients(path)
    F, D, rel_err = factor_flow_distance(coef)
    info = {
        "archivo": path.name,
        "variables_y": n_y,
        "variables_x": F.shape[0] ** 2,
        "error_factorizacion": rel_err,
        "D_simetrica": bool(np.allclose(D, D.T)),
    }
    return QAPInstance(F, D), info


def verify_scale(inst: QAPInstance, coef: np.ndarray, perm) -> tuple[float, float]:
    """Compara C(perm) con F,D frente a la suma directa de los coeficientes del MPS."""
    direct = sum(coef[i, perm[i], k, perm[k]] for i in range(inst.n) for k in range(i + 1, inst.n))
    return inst.cost(perm), float(direct)
