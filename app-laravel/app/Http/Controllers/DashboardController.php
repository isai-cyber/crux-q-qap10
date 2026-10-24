<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\ProductoCatalogo;
use App\Models\Ticket;

class DashboardController extends Controller
{
    public function index()
    {
        $kpis = [
            'tickets_abiertos' => Ticket::whereIn('estado', ['abierto', 'en_progreso', 'esperando_repuesto'])->count(),
            'tickets_criticos' => Ticket::where('estado', '!=', 'cerrado')
                ->where(function ($q) {
                    $q->where('prioridad_final', 'critica')
                        ->orWhere(fn ($q2) => $q2->whereNull('prioridad_final')->where('prioridad_ia', 'critica'));
                })->count(),
            'activos_fuera_servicio' => Activo::where('estado', 'fuera_servicio')->count(),
            'productos_sin_stock' => ProductoCatalogo::whereDoesntHave('inventarios', function ($q) {
                $q->where('organizacion_id', auth()->user()->organizacion_id)->where('stock_actual', '>', 0);
            })->count(),
        ];

        $ticketsRecientes = Ticket::with('activo')->orderByDesc('fecha_apertura')->limit(8)->get();

        return view('dashboard.index', compact('kpis', 'ticketsRecientes'));
    }
}
