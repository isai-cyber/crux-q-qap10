@extends('layouts.app')

@section('title', $producto->numero_parte)

@section('content')
    <h2 class="mb-3">{{ $producto->numero_parte }}</h2>

    <div class="card" style="max-width: 640px;">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-4">Descripción</dt><dd class="col-8">{{ $producto->descripcion }}</dd>
                <dt class="col-4">Categoría</dt><dd class="col-8">{{ $producto->categoria->nombre ?? '—' }}</dd>
                <dt class="col-4">Stock actual</dt><dd class="col-8">{{ $stock }}</dd>
                <dt class="col-4">Precio de lista</dt><dd class="col-8">{{ $producto->precio_lista !== null ? '$'.number_format($producto->precio_lista, 2) : '—' }}</dd>
                <dt class="col-4">Vigencia catálogo</dt><dd class="col-8">{{ $producto->vigencia_catalogo ?? '—' }}</dd>
                <dt class="col-4">Origen</dt><dd class="col-8">{{ $producto->origen_documento ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <a href="{{ route('catalogo.index') }}" class="btn btn-outline-secondary mt-3">Volver al catálogo</a>
@endsection
