@extends('layouts.app')

@section('title', 'Catálogo')

@section('content')
    <h2 class="mb-3">Catálogo Siemens SIRIUS</h2>

    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Número de parte o descripción..." value="{{ request('q') }}">
        </div>
        <div class="col-md-3">
            <select name="categoria" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categorias as $c)
                    <option value="{{ $c->id }}" @selected((int) request('categoria') === $c->id)>{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>N° Parte</th><th>Descripción</th><th>Categoría</th><th>Stock</th><th>Precio lista</th><th></th></tr></thead>
                <tbody>
                @forelse ($productos as $p)
                    <tr>
                        <td><code>{{ $p->numero_parte }}</code></td>
                        <td>{{ $p->descripcion }}</td>
                        <td>{{ $p->categoria->nombre ?? '—' }}</td>
                        <td>{{ (int) $p->stock_actual === 0 ? '0' : (int) $p->stock_actual }}</td>
                        <td>{{ $p->precio_lista !== null ? '$'.number_format($p->precio_lista, 2) : '—' }}</td>
                        <td><a href="{{ route('catalogo.show', $p) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">Sin resultados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $productos->links() }}</div>
@endsection
