<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function index()
    {
        $porEstado = Ticket::select('estado', DB::raw('count(*) as total'))->groupBy('estado')->get();

        $porPlanta = Ticket::join('activos', 'activos.id', '=', 'tickets.activo_id')
            ->join('plantas', 'plantas.id', '=', 'activos.planta_id')
            ->select('plantas.nombre as planta', DB::raw('count(tickets.id) as total'))
            ->groupBy('plantas.nombre')->get();

        $consumoRepuestos = MovimientoStock::join('productos_catalogo', 'productos_catalogo.id', '=', 'movimientos_stock.producto_id')
            ->where('movimientos_stock.tipo', 'salida')
            ->select('productos_catalogo.numero_parte', 'productos_catalogo.descripcion', DB::raw('sum(movimientos_stock.cantidad) as total_consumido'))
            ->groupBy('productos_catalogo.id', 'productos_catalogo.numero_parte', 'productos_catalogo.descripcion')
            ->orderByDesc('total_consumido')->limit(10)->get();

        return view('modulos.reportes.index', compact('porEstado', 'porPlanta', 'consumoRepuestos'));
    }

    public function exportarTickets(Request $request)
    {
        $query = Ticket::with('activo')->orderByDesc('fecha_apertura');
        if ($estado = $request->get('estado')) {
            $query->where('estado', $estado);
        }
        $tickets = $query->get();

        $callback = function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Folio', 'Descripción', 'Categoría', 'Prioridad', 'Estado', 'Apertura', 'Cierre', 'Activo'], ',', '"', '\\');
            foreach ($tickets as $t) {
                fputcsv($out, [
                    $t->folio, $t->descripcion, $t->categoria_final, $t->prioridad_final,
                    $t->estado, $t->fecha_apertura, $t->fecha_cierre, $t->activo->nombre ?? '',
                ], ',', '"', '\\');
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'tickets.csv', ['Content-Type' => 'text/csv']);
    }
}
