@extends('layouts.app')

@section('title', $activo ? 'Editar activo' : 'Nuevo activo')

@section('content')
    <h2 class="mb-3">{{ $activo ? 'Editar activo' : 'Nuevo activo' }}</h2>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $activo ? route('activos.update', $activo) : route('activos.store') }}" class="card p-4" style="max-width: 640px;">
        @csrf
        @if ($activo) @method('PUT') @endif

        <div class="mb-3">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $activo->nombre ?? '') }}" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Tipo</label>
            <input type="text" name="tipo" class="form-control" value="{{ old('tipo', $activo->tipo ?? '') }}" placeholder="PLC, CNC, contactor...">
        </div>

        <div class="mb-3">
            <label class="form-label">Planta *</label>
            <select name="planta_id" class="form-select" required>
                <option value="">-- Seleccione --</option>
                @foreach ($plantas as $p)
                    <option value="{{ $p->id }}" @selected(old('planta_id', $activo->planta_id ?? null) == $p->id)>{{ $p->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Ubicación</label>
            <input type="text" name="ubicacion" class="form-control" value="{{ old('ubicacion', $activo->ubicacion ?? '') }}">
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Dirección IP</label>
                <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address', $activo->ip_address ?? '') }}" placeholder="192.168.1.10">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Nombre PROFINET</label>
                <input type="text" name="nombre_profinet" class="form-control" value="{{ old('nombre_profinet', $activo->nombre_profinet ?? '') }}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Versión de firmware</label>
                <input type="text" name="version_firmware" class="form-control" value="{{ old('version_firmware', $activo->version_firmware ?? '') }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha puesta en marcha</label>
                <input type="date" name="fecha_puesta_marcha" class="form-control" value="{{ old('fecha_puesta_marcha', optional($activo->fecha_puesta_marcha ?? null)->format('Y-m-d')) }}">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                @foreach (['operativo', 'en_mantenimiento', 'fuera_servicio'] as $estado)
                    <option value="{{ $estado }}" @selected(old('estado', $activo->estado ?? 'operativo') === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('activos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </form>
@endsection
