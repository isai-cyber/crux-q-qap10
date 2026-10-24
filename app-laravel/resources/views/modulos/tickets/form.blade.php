@extends('layouts.app')

@section('title', 'Nuevo ticket')

@section('content')
    <h2 class="mb-3">Reportar falla</h2>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('tickets.store') }}" class="card p-4" style="max-width: 640px;">
        @csrf
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
            <label class="form-label">Descripción de la falla *</label>
            <textarea name="descripcion" class="form-control" rows="4" required placeholder="Ej: el contactor de la línea 2 no arranca"></textarea>
            <div class="form-text">La prioridad y categoría se clasificarán automáticamente con IA al guardar.</div>
        </div>

        <button type="submit" class="btn btn-primary">Crear ticket</button>
        <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </form>
@endsection
