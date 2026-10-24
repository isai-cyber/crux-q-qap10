<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    private const PERMISOS_VALIDOS = [
        'crear_ticket', 'consultar_activos', 'crear_activo', 'consultar_catalogo', 'consultar_alertas', 'crear_usuario',
    ];

    public function index(Request $request)
    {
        $tokens = ApiToken::orderByDesc('created_at')->get();
        $tokenPlano = $request->session()->pull('nuevo_token_plano');

        return view('modulos.tokens.index', compact('tokens', 'tokenPlano'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'permisos' => ['nullable', 'array'],
        ]);

        $permisos = array_values(array_intersect($data['permisos'] ?? [], self::PERMISOS_VALIDOS));
        $tokenPlano = bin2hex(random_bytes(32));

        ApiToken::create([
            'organizacion_id' => auth()->user()->organizacion_id,
            'nombre' => $data['nombre'],
            'token_hash' => hash('sha256', $tokenPlano),
            'permisos' => $permisos,
            'usuario_creador_id' => auth()->id(),
            'activo' => true,
        ]);

        $request->session()->put('nuevo_token_plano', $tokenPlano);

        return redirect()->route('tokens.index');
    }

    public function revoke(ApiToken $token)
    {
        abort_unless($token->organizacion_id === auth()->user()->organizacion_id, 404);
        $token->update(['activo' => false, 'revoked_at' => now()]);

        return redirect()->route('tokens.index')->with('status', 'Token revocado.');
    }
}
