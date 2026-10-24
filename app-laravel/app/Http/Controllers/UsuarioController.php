<?php

namespace App\Http\Controllers;

use App\Models\Planta;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = Usuario::deMiOrganizacion()->orderBy('nombre')->get();
        $plantas = Planta::orderBy('nombre')->get();

        return view('modulos.usuarios.index', compact('usuarios', 'plantas'));
    }

    public function store(Request $request)
    {
        $org = auth()->user()->organizacion_id;

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('usuarios')->where('organizacion_id', $org)],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', 'in:admin,supervisor,tecnico'],
            'planta_id' => ['nullable', 'exists:plantas,id'],
        ]);

        Usuario::create([
            'organizacion_id' => $org,
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'password_hash' => bcrypt($data['password']),
            'rol' => $data['rol'],
            'planta_id' => $data['planta_id'] ?? null,
            'activo' => true,
        ]);

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado.');
    }

    public function update(Request $request, Usuario $usuario)
    {
        abort_unless($usuario->organizacion_id === auth()->user()->organizacion_id, 404);

        $usuario->update(['activo' => $request->boolean('activo')]);

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado.');
    }
}
