@extends('layouts.app')

@section('title', $activo->nombre)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ $activo->nombre }}</h2>
        <div>
            @if (in_array(auth()->user()->rol, ['admin', 'supervisor']) && Route::has('activos.edit'))
                <a href="{{ route('activos.edit', $activo) }}" class="btn btn-outline-secondary">Editar</a>
            @endif
            @if (auth()->user()->rol === 'admin' && Route::has('activos.destroy'))
                <form method="POST" action="{{ route('activos.destroy', $activo) }}" class="d-inline" data-confirm="¿Eliminar este activo? Esta acción no se puede deshacer.">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Datos del activo</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Tipo</dt><dd class="col-7">{{ $activo->tipo ?? '—' }}</dd>
                        <dt class="col-5">Planta</dt><dd class="col-7">{{ $activo->planta->nombre }}</dd>
                        <dt class="col-5">Ubicación</dt><dd class="col-7">{{ $activo->ubicacion ?? '—' }}</dd>
                        <dt class="col-5">IP</dt><dd class="col-7">{{ $activo->ip_address ?? '—' }}</dd>
                        <dt class="col-5">Nombre PROFINET</dt><dd class="col-7">{{ $activo->nombre_profinet ?? '—' }}</dd>
                        <dt class="col-5">Firmware</dt><dd class="col-7">{{ $activo->version_firmware ?? '—' }}</dd>
                        <dt class="col-5">Estado</dt><dd class="col-7"><span class="badge bg-light text-dark">{{ $activo->estado }}</span></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Historial de diagnóstico</div>
                <ul class="list-group list-group-flush">
                    @forelse ($activo->diagnosticos as $d)
                        <li class="list-group-item">
                            <span class="badge bg-secondary">{{ $d->severidad }}</span> {{ $d->descripcion }}
                            <div class="small text-secondary">{{ $d->fecha_diagnostico }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">Sin diagnósticos registrados.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
