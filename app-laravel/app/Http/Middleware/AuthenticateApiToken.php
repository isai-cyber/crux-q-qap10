<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $permiso = null): Response
    {
        $header = $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return response()->json(['error' => 'Falta el token de autorización.'], 401);
        }

        $token = ApiToken::where('token_hash', hash('sha256', $m[1]))->where('activo', true)->first();
        if (! $token) {
            return response()->json(['error' => 'Token inválido o revocado.'], 401);
        }

        if ($permiso !== null && ! $token->tienePermiso($permiso)) {
            return response()->json(['error' => "El token no tiene el permiso '{$permiso}'."], 403);
        }

        $token->update(['ultimo_uso' => now()]);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
