@extends('layouts.app')

@section('title', 'Nuevo diseño técnico')

@section('content')
    <h2 class="mb-3">Nuevo diseño técnico con IA</h2>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('disenos.store') }}" class="card p-4" style="max-width: 700px;">
        @csrf
        <div class="mb-3">
            <label class="form-label">Tipo de diseño *</label>
            <select name="tipo" class="form-select" required>
                <option value="diagrama">Diagrama de control / cableado (SVG)</option>
                <option value="documento">Documento técnico (procedimiento, alcance, BOM)</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Activo relacionado</label>
            <select name="activo_id" class="form-select">
                <option value="">-- Sin especificar --</option>
                @foreach ($activos as $a)
                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Productos del catálogo a incluir (opcional, solo diagramas)</label>
            <select name="producto_ids[]" class="form-select" multiple size="5">
                @foreach ($productos as $p)
                    <option value="{{ $p->id }}">{{ $p->numero_parte }} — {{ $p->descripcion }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción de lo que necesitas *</label>
            <textarea name="descripcion_solicitud" class="form-control" rows="4" required
                placeholder="Ej: arranque directo con protección para motor trifásico 15kW/400V con paro de emergencia"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Generar con IA</button>
        <a href="{{ route('disenos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </form>
@endsection
