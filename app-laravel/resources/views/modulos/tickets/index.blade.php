@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Tickets</h2>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary">+ Nuevo ticket</a>
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <select name="estado" class="form-select" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                @foreach (['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'] as $estado)
                    <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Folio</th><th>Activo</th><th>Canal</th><th>Prioridad</th><th>Estado</th><th>Apertura</th><th></th></tr></thead>
                <tbody>
                @forelse ($tickets as $t)
                    @php $prioridad = $t->prioridad_final ?? $t->prioridad_ia; @endphp
                    <tr>
                        <td>{{ $t->folio }}</td>
                        <td>{{ $t->activo->nombre ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $t->canal_origen }}</span></td>
                        <td>@if ($prioridad)<span class="badge badge-prioridad-{{ $prioridad }}">{{ $prioridad }}</span>@endif</td>
                        <td><span class="badge bg-light text-dark">{{ $t->estado }}</span></td>
                        <td>{{ $t->fecha_apertura }}</td>
                        <td><a href="{{ route('tickets.show', $t) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-3">Sin tickets.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $tickets->links() }}</div>
@endsection
