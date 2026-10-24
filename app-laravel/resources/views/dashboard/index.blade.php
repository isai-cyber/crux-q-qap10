@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h2 class="mb-4">Dashboard</h2>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="text-secondary small">Tickets abiertos</div>
                    <div class="fs-2 fw-bold">{{ $kpis['tickets_abiertos'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="text-secondary small">Tickets críticos activos</div>
                    <div class="fs-2 fw-bold text-danger">{{ $kpis['tickets_criticos'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="text-secondary small">Activos fuera de servicio</div>
                    <div class="fs-2 fw-bold">{{ $kpis['activos_fuera_servicio'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-secondary">
                <div class="card-body">
                    <div class="text-secondary small">Productos sin stock</div>
                    <div class="fs-2 fw-bold">{{ $kpis['productos_sin_stock'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Tickets recientes</span>
            <a href="{{ route('tickets.create') }}" class="btn btn-sm btn-primary">+ Nuevo ticket</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Folio</th><th>Activo</th><th>Descripción</th><th>Prioridad IA</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($ticketsRecientes as $t)
                    <tr>
                        <td>{{ $t->folio }}</td>
                        <td>{{ $t->activo->nombre ?? '—' }}</td>
                        <td>{{ Str::limit($t->descripcion, 60) }}</td>
                        <td>@if ($t->prioridad_ia)<span class="badge badge-prioridad-{{ $t->prioridad_ia }}">{{ $t->prioridad_ia }}</span>@endif</td>
                        <td><span class="badge bg-light text-dark">{{ $t->estado }}</span></td>
                        <td><a href="{{ route('tickets.show', $t) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">Sin tickets registrados aún.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
