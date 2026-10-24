@extends('layouts.app')

@section('title', 'Activos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Activos (CMDB)</h2>
        @if (Route::has('activos.create'))
            <a href="{{ route('activos.create') }}" class="btn btn-primary">+ Nuevo activo</a>
        @endif
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, IP, nombre PROFINET..." value="{{ request('q') }}">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Nombre</th><th>Planta</th><th>IP</th><th>Firmware</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($activos as $activo)
                    <tr>
                        <td>{{ $activo->nombre }}</td>
                        <td>{{ $activo->planta->nombre }}</td>
                        <td>{{ $activo->ip_address ?? '—' }}</td>
                        <td>{{ $activo->version_firmware ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $activo->estado }}</span></td>
                        <td><a href="{{ route('activos.show', $activo) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">Sin activos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $activos->links() }}</div>
@endsection
