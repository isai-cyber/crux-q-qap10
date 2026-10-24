<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Planta;
use Illuminate\Http\Request;

class ActivoController extends Controller
{
    public function index(Request $request)
    {
        $query = Activo::with('planta')->orderBy('nombre');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('nombre_profinet', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $activos = $query->paginate(20)->withQueryString();

        return view('modulos.activos.index', compact('activos'));
    }

    public function create()
    {
        $plantas = Planta::orderBy('nombre')->get();

        return view('modulos.activos.form', ['activo' => null, 'plantas' => $plantas]);
    }

    public function store(Request $request)
    {
        $data = $this->validado($request);
        $activo = Activo::create($data);

        return redirect()->route('activos.show', $activo)->with('status', 'Activo creado.');
    }

    public function show(Activo $activo)
    {
        $activo->load(['planta', 'diagnosticos']);

        return view('modulos.activos.show', compact('activo'));
    }

    public function edit(Activo $activo)
    {
        $plantas = Planta::orderBy('nombre')->get();

        return view('modulos.activos.form', compact('activo', 'plantas'));
    }

    public function update(Request $request, Activo $activo)
    {
        $data = $this->validado($request);
        $activo->update($data);

        return redirect()->route('activos.show', $activo)->with('status', 'Activo actualizado.');
    }

    public function destroy(Activo $activo)
    {
        $activo->delete();

        return redirect()->route('activos.index')->with('status', 'Activo eliminado.');
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'planta_id' => ['required', 'exists:plantas,id'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'nombre_profinet' => ['nullable', 'string', 'max:120'],
            'version_firmware' => ['nullable', 'string', 'max:60'],
            'fecha_puesta_marcha' => ['nullable', 'date'],
            'estado' => ['required', 'in:operativo,en_mantenimiento,fuera_servicio'],
        ]);
    }
}
