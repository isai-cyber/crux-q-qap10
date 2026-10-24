<?php

use App\Http\Controllers\ActivoController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisenoTecnicoController;
use App\Http\Controllers\GrafoController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\McpConexionController;
use App\Http\Controllers\OrdenTrabajoController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RunbookController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Servidor MCP: en la raíz, sin sesión/CSRF (ver bootstrap/app.php).
Route::post('/mcp', [McpController::class, 'handle'])->name('mcp.handle');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/registro', [RegistroController::class, 'showForm'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
    Route::get('/catalogo/{producto}', [CatalogoController::class, 'show'])->name('catalogo.show');

    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/activos/nuevo', [ActivoController::class, 'create'])->name('activos.create');
        Route::post('/activos', [ActivoController::class, 'store'])->name('activos.store');
        Route::get('/activos/{activo}/editar', [ActivoController::class, 'edit'])->name('activos.edit');
        Route::put('/activos/{activo}', [ActivoController::class, 'update'])->name('activos.update');
    });
    Route::middleware('role:admin')->group(function () {
        Route::delete('/activos/{activo}', [ActivoController::class, 'destroy'])->name('activos.destroy');
    });
    Route::get('/activos', [ActivoController::class, 'index'])->name('activos.index');
    Route::get('/activos/{activo}', [ActivoController::class, 'show'])->name('activos.show');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/nuevo', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/estado', [TicketController::class, 'updateStatus'])->name('tickets.estado');

    Route::get('/ordenes', [OrdenTrabajoController::class, 'index'])->name('ordenes.index');
    Route::get('/ordenes/{orden}', [OrdenTrabajoController::class, 'show'])->name('ordenes.show');
    Route::post('/ordenes/{orden}', [OrdenTrabajoController::class, 'update'])->name('ordenes.update');

    Route::get('/runbooks', [RunbookController::class, 'index'])->name('runbooks.index');

    Route::get('/disenos', [DisenoTecnicoController::class, 'index'])->name('disenos.index');
    Route::get('/disenos/nuevo', [DisenoTecnicoController::class, 'create'])->name('disenos.create');
    Route::post('/disenos', [DisenoTecnicoController::class, 'store'])->name('disenos.store');
    Route::get('/disenos/{diseno}', [DisenoTecnicoController::class, 'show'])->name('disenos.show');

    Route::get('/conocimiento', [GrafoController::class, 'index'])->name('conocimiento.index');

    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/tickets/exportar', [ReporteController::class, 'exportarTickets'])->name('reportes.exportar-tickets');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/enviar', [ChatController::class, 'enviar'])->name('chat.enviar');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/admin/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::put('/admin/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');

        Route::get('/admin/tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
        Route::post('/admin/tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
        Route::delete('/admin/tokens/{token}', [ApiTokenController::class, 'revoke'])->name('tokens.revoke');

        Route::get('/admin/mcp', [McpConexionController::class, 'index'])->name('mcp.index');
        Route::post('/admin/mcp', [McpConexionController::class, 'store'])->name('mcp.store');
        Route::post('/admin/mcp/{conexion}/verificar', [McpConexionController::class, 'verificarAccion'])->name('mcp.verificar');
        Route::delete('/admin/mcp/{conexion}', [McpConexionController::class, 'destroy'])->name('mcp.destroy');
    });
});
