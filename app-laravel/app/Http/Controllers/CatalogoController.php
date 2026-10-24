<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProducto;
use App\Models\ProductoCatalogo;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        $organizacionId = auth()->user()->organizacion_id;

        $query = ProductoCatalogo::with('categoria')
            ->leftJoin('inventario', function ($join) use ($organizacionId) {
                $join->on('inventario.producto_id', '=', 'productos_catalogo.id')
                    ->where('inventario.organizacion_id', $organizacionId);
            })
            ->select('productos_catalogo.*', 'inventario.stock_actual as stock_actual')
            ->orderBy('numero_parte');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('numero_parte', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($categoriaId = $request->get('categoria')) {
            $query->where('categoria_id', $categoriaId);
        }

        $productos = $query->paginate(20)->withQueryString();
        $categorias = CategoriaProducto::orderBy('nombre')->get();

        return view('modulos.catalogo.index', compact('productos', 'categorias'));
    }

    public function show(ProductoCatalogo $producto)
    {
        $producto->load('categoria');
        $stock = $producto->stockActual();

        return view('modulos.catalogo.show', compact('producto', 'stock'));
    }
}
