<?php

namespace App\Http\Controllers;

use App\Models\Organizacion;
use App\Models\Planta;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistroController extends Controller
{
    public function showForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.registro');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organizacion' => ['required', 'string', 'max:150'],
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $usuario = DB::transaction(function () use ($data) {
            $slug = $this->generarSlugUnico($data['organizacion']);

            $organizacion = Organizacion::create([
                'nombre' => $data['organizacion'],
                'slug' => $slug,
                'plan' => 'trial',
            ]);

            $planta = Planta::create([
                'organizacion_id' => $organizacion->id,
                'nombre' => 'Planta principal',
            ]);

            return Usuario::create([
                'organizacion_id' => $organizacion->id,
                'nombre' => $data['nombre'],
                'email' => $data['email'],
                'password_hash' => bcrypt($data['password']),
                'rol' => 'admin',
                'planta_id' => $planta->id,
            ]);
        });

        Auth::login($usuario);

        return redirect()->route('dashboard');
    }

    private function generarSlugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'empresa';
        $slug = $base;
        $intento = 1;

        while (Organizacion::where('slug', $slug)->exists()) {
            $intento++;
            $slug = "{$base}-{$intento}";
        }

        return $slug;
    }
}
