<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// API REST (docs/API.md) — autenticación por token Bearer, sin sesión web.
// Prefijo /api automático (withRouting), sin CSRF (grupo "api" ya es stateless).
Route::middleware('api.token')->group(function () {
    Route::post('/v1/tickets', [ApiController::class, 'crearTicket']);
    Route::get('/v1/activos/{id}', [ApiController::class, 'consultarActivo']);
    Route::post('/v1/activos', [ApiController::class, 'crearActivo']);
    Route::post('/v1/usuarios', [ApiController::class, 'crearUsuario']);
    Route::get('/v1/catalogo/buscar', [ApiController::class, 'buscarCatalogo']);
    Route::get('/v1/alertas/pendientes', [ApiController::class, 'alertasPendientes']);
});
