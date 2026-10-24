"""Ejecución puntual de QAOA sobre una subinstancia (la usa el módulo web de Crux).

    .venv/bin/python cli.py --n 3 --mixer xy --reps 2 --seed 7
Imprime un JSON en stdout.
"""
import argparse
import json

from crux_q.classical import brute_force
from crux_q.instance import load_qap10
from crux_q.qaoa import QAOAConfig, run_qaoa


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int, choices=(3, 4), default=3)
    ap.add_argument("--mixer", choices=("x", "xy"), default="xy")
    ap.add_argument("--reps", type=int, choices=(1, 2, 3), default=2)
    ap.add_argument("--seed", type=int, default=7)
    a = ap.parse_args()
    inst, _ = load_qap10()
    s = inst.sub(a.n)
    exact = brute_force(s)
    r = run_qaoa(s.F, s.D, QAOAConfig(mixer=a.mixer, reps=a.reps, seed=a.seed, maxiter=100, restarts=1),
                 optimum=exact["costo"], cost_fn=s.cost)
    print(json.dumps({
        "n": a.n, "mixer": a.mixer, "reps": a.reps, "semilla": a.seed,
        "optimo_exacto": exact["costo"], "tiempo_exacto_s": exact["tiempo_s"],
        "costo": r.best_cost, "perm": r.best_perm, "fraccion_factible": r.feasible_fraction,
        "prob_optimo": r.prob_optimal, "qubits": r.num_qubits, "profundidad": r.depth,
        "puertas_2q": r.two_qubit_gates, "evaluaciones": r.evaluations, "tiempo_s": r.time_s,
        "lambda": r.lam, "top": r.top,
    }, ensure_ascii=False))


if __name__ == "__main__":
    main()
