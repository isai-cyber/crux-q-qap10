"""Corre todos los experimentos del reto QAP10 y escribe results/results.json.

    .venv/bin/python run_experiments.py           # completo (~10-15 min en un portátil)
    .venv/bin/python run_experiments.py --quick   # versión corta para comprobar que todo funciona
"""
from __future__ import annotations

import argparse
import itertools
import json
import math
import platform
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

import numpy as np
import qiskit
import qiskit_aer
import scipy

from crux_q.classical import brute_force, multistart_local_search, simulated_annealing
from crux_q.hybrid import lns
from crux_q.instance import CONTROL_OPTIMA, OPTIMUM_FULL, load_qap10, reconstruct_coefficients, verify_scale
from crux_q.qaoa import QAOAConfig, build_circuit, run_qaoa
from crux_q.qubo import build_qubo, decode, default_lambda, qubo_to_ising

OUT = Path(__file__).resolve().parent / "results"


def log(msg):
    print(msg, flush=True)


def gap(c):
    return None if c is None else 100.0 * (c - OPTIMUM_FULL) / OPTIMUM_FULL


def summarize(runs, key="costo"):
    vals = [r[key] for r in runs if r.get(key) is not None]
    return {"mejor": min(vals) if vals else None, "media": float(np.mean(vals)) if vals else None,
            "peor": max(vals) if vals else None, "n": len(runs)}


def stage_model():
    log("== Modelo y datos ==")
    inst, info = load_qap10()
    coef, _ = reconstruct_coefficients()
    bf = brute_force(inst)
    c_fd, c_mps = verify_scale(inst, coef, bf["perm"])
    controls = {}
    for n, opt in CONTROL_OPTIMA.items():
        s = inst.sub(n)
        b = brute_force(s)
        controls[str(n)] = {"optimo_calculado": b["costo"], "optimo_control": opt, "perm": b["perm"],
                            "coincide": abs(b["costo"] - opt) < 1e-9}
    model = {**info, "F": inst.F.astype(int).tolist(), "D": inst.D.astype(int).tolist(),
             "optimo_enumerado": bf["costo"], "perm_optima": bf["perm"], "tiempo_enumeracion_s": bf["tiempo_s"],
             "costo_FD_en_perm_optima": c_fd, "costo_MPS_directo_en_perm_optima": c_mps,
             "controles": controls}
    log(f"  óptimo enumerado = {bf['costo']} (MPS directo {c_mps}); controles {controls}")
    return inst, model


def random_baselines(n, opt_count):
    """Probabilidad de obtener una asignación factible / óptima muestreando al azar."""
    return {"uniforme_bits": {"factible": math.factorial(n) / 2 ** (n * n), "optima": opt_count / 2 ** (n * n)},
            "uniforme_por_fila_W": {"factible": math.factorial(n) / n ** n, "optima": opt_count / n ** n}}


def stage1(inst, quick):
    log("== Etapa 1: N=3 y N=4 ==")
    out = {}
    for n in (3, 4):
        s = inst.sub(n)
        opt = CONTROL_OPTIMA[n]
        perms = list(itertools.permutations(range(n)))
        opt_count = sum(abs(s.cost(p) - opt) < 1e-9 for p in perms)
        bf = brute_force(s)
        sa = [simulated_annealing(s, seed=k, iters=2000) for k in range(5)]
        qubo = build_qubo(s.F, s.D)
        E = qubo.energies_all()
        g = int(np.argmin(E))
        gs_perm = decode((g >> np.arange(n * n)) & 1, n)

        # Barrido de λ: ¿sigue siendo factible el mínimo del QUBO?
        lam_sweep = []
        lam0 = default_lambda(s.F, s.D)
        for factor in (0.1, 0.25, 0.5, 1.0, 2.0):
            q = build_qubo(s.F, s.D, lam=lam0 * factor)
            e = q.energies_all()
            gi = int(np.argmin(e))
            p = decode((gi >> np.arange(n * n)) & 1, n)
            feas_E = [q.energy(np.eye(n)[list(pp)].reshape(-1)) for pp in perms]
            lam_sweep.append({"factor": factor, "lambda": q.lam, "E_min": float(e.min()),
                              "minimo_factible": p is not None, "costo_minimo": s.cost(p) if p else None,
                              "brecha_espectral_infactible": float(min(e[[i for i in range(len(e)) if decode((i >> np.arange(n * n)) & 1, n) is None]]) - min(feas_E))})

        qaoa_runs = []
        seeds = (11,) if quick else (11, 23, 37)
        reps_list = (1, 2) if quick else (1, 2, 3)
        for mixer in ("x", "xy"):
            for reps in reps_list:
                for sd in seeds:
                    cfg = QAOAConfig(mixer=mixer, reps=reps, seed=sd, maxiter=60 if quick else 150, restarts=1 if quick else 2)
                    r = run_qaoa(s.F, s.D, cfg, optimum=opt, cost_fn=s.cost)
                    qaoa_runs.append({"mixer": mixer, "reps": reps, "semilla": sd, "costo": r.best_cost, "perm": r.best_perm,
                                      "fraccion_factible": r.feasible_fraction, "prob_optimo": r.prob_optimal,
                                      "energia_esperada": r.expected_energy, "qubits": r.num_qubits,
                                      "profundidad": r.depth, "puertas_2q": r.two_qubit_gates,
                                      "evaluaciones": r.evaluations, "tiempo_s": r.time_s, "lambda": r.lam,
                                      "parametros": r.params, "top": r.top})
                    log(f"  N={n} QAOA-{mixer} p={reps} seed={sd}: costo={r.best_cost} factible={r.feasible_fraction:.3f} "
                        f"P(opt)={r.prob_optimal:.4f} t={r.time_s:.1f}s")
        # λ reducido con XY (la penalización de filas ya no se necesita: solo columnas)
        lam_runs = []
        for factor in ((0.5, 1.0) if quick else (0.25, 0.5, 1.0)):
            cfg = QAOAConfig(mixer="xy", reps=2, seed=11, lam=lam0 * factor, maxiter=60 if quick else 150, restarts=1 if quick else 2)
            r = run_qaoa(s.F, s.D, cfg, optimum=opt, cost_fn=s.cost)
            lam_runs.append({"factor": factor, "lambda": r.lam, "costo": r.best_cost,
                             "fraccion_factible": r.feasible_fraction, "prob_optimo": r.prob_optimal, "tiempo_s": r.time_s})
            log(f"  N={n} QAOA-xy λ×{factor}: costo={r.best_cost} factible={r.feasible_fraction:.3f} P(opt)={r.prob_optimal:.4f}")
        out[str(n)] = {
            "optimo_control": opt, "num_optimos": opt_count, "permutaciones": len(perms),
            "variables_qubo": n * n, "lambda_por_defecto": lam0,
            "clasico": {"enumeracion": bf, "recocido": sa, "recocido_resumen": summarize(sa)},
            "qubo_estado_fundamental": {"E_min": float(E.min()), "perm": gs_perm,
                                        "costo": s.cost(gs_perm) if gs_perm else None},
            "barrido_lambda_qubo": lam_sweep, "qaoa": qaoa_runs, "qaoa_lambda": lam_runs,
            "azar": random_baselines(n, opt_count),
        }
    return out


def stage_full(inst, quick):
    log("== Instancia completa N=10 ==")
    res = {}
    sa = [simulated_annealing(inst, seed=k, iters=20_000) for k in range(3 if quick else 10)]
    res["recocido"] = {"runs": sa, "resumen": summarize(sa)}
    ms = [multistart_local_search(inst, starts=s, seed=k) for k in range(3 if quick else 10) for s in (1, 5, 20)]
    res["busqueda_local"] = {"runs": ms}
    log(f"  recocido: {summarize(sa)}")

    lns_seeds = range(2 if quick else 5)
    res["lns_exacto"] = {}
    for k in (3, 4):
        runs = [lns(inst, k=k, iters=20 if quick else 60, seed=sd, solver="exact") for sd in lns_seeds]
        res["lns_exacto"][str(k)] = {"runs": runs, "resumen": summarize(runs)}
        log(f"  LNS exacto k={k}: {summarize(runs)}")
    res["lns_qaoa"] = {}
    q_plan = {3: (lns_seeds, 20 if quick else 60, QAOAConfig(reps=2, mixer="xy", maxiter=60, restarts=1, shots=1024)),
              4: (range(1 if quick else 2), 8 if quick else 30, QAOAConfig(reps=1, mixer="xy", maxiter=40, restarts=1, shots=1024))}
    for k, (seeds, iters, cfg) in q_plan.items():
        runs = [lns(inst, k=k, iters=iters, seed=sd, solver="qaoa", qaoa_cfg=cfg) for sd in seeds]
        res["lns_qaoa"][str(k)] = {"runs": runs, "resumen": summarize(runs), "config": cfg.__dict__}
        log(f"  LNS QAOA k={k}: {summarize(runs)}")
    for block in (res["lns_exacto"], res["lns_qaoa"]):
        for v in block.values():
            for r in v["runs"]:
                r["gap_pct"] = gap(r["costo"])
    for r in sa + ms:
        r["gap_pct"] = gap(r["costo"])
    return res


def stage_scaling(inst, quick):
    log("== Etapa 3: escalamiento ==")
    rows = []
    for n in range(3, 11):
        s = inst.sub(n)
        bf = brute_force(s) if n <= (9 if quick else 10) else None
        sa = simulated_annealing(s, seed=0, iters=5000)
        qubo = build_qubo(s.F, s.D)
        ising = qubo_to_ising(qubo)
        circ = {}
        for mixer in ("x", "xy"):
            qc = build_circuit(ising, n, 1, mixer).decompose(reps=2)
            ops = qc.count_ops()
            circ[mixer] = {"profundidad_p1": qc.depth(), "puertas_2q_p1": int(sum(v for k, v in ops.items() if k in ("rzz", "cx", "cz")))}
        m = n * n
        rows.append({"N": n, "variables_qubo": m, "terminos_ising": len(ising), "permutaciones": math.factorial(n),
                     "espacio_busqueda_qubo": f"2^{m}", "fraccion_factible_qubo": math.factorial(n) / 2 ** m,
                     "memoria_statevector_bytes": 16 * 2 ** m,
                     "optimo_exacto": bf["costo"] if bf else None, "tiempo_exacto_s": bf["tiempo_s"] if bf else None,
                     "recocido_costo": sa["costo"], "recocido_tiempo_s": sa["tiempo_s"], "circuito": circ})
        log(f"  N={n}: exacto={rows[-1]['optimo_exacto']} SA={sa['costo']} qubits={m} 2q(p=1,xy)={circ['xy']['puertas_2q_p1']}")
    return rows


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--quick", action="store_true")
    args = ap.parse_args()
    t0 = time.perf_counter()
    inst, model = stage_model()
    results = {
        "generado": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "modo": "rapido" if args.quick else "completo",
        "entorno": {"python": sys.version.split()[0], "qiskit": qiskit.__version__, "qiskit_aer": qiskit_aer.__version__,
                    "numpy": np.__version__, "scipy": scipy.__version__, "plataforma": platform.platform()},
        "optimo_referencia": OPTIMUM_FULL,
        "modelo": model,
        "etapa1": stage1(inst, args.quick),
        "completa": stage_full(inst, args.quick),
        "escalamiento": stage_scaling(inst, args.quick),
    }
    results["tiempo_total_s"] = time.perf_counter() - t0
    OUT.mkdir(exist_ok=True)
    path = OUT / ("results_quick.json" if args.quick else "results.json")
    path.write_text(json.dumps(results, ensure_ascii=False, indent=1, default=float))
    log(f"Listo en {results['tiempo_total_s']:.0f}s -> {path}")


if __name__ == "__main__":
    main()
