@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    <h2 class="mb-3">Reportes</h2>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Tickets por estado
                    <a href="{{ route('reportes.exportar-tickets') }}" class="btn btn-sm btn-outline-secondary">Exportar CSV</a>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($porEstado as $row)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $row->estado }}</span><strong>{{ $row->total }}</strong></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Tickets por planta</div>
                <ul class="list-group list-group-flush">
                    @forelse ($porPlanta as $row)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $row->planta }}</span><strong>{{ $row->total }}</strong></li>
                    @empty
                        <li class="list-group-item text-secondary">Sin datos suficientes.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">Repuestos más consumidos</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>N° Parte</th><th>Descripción</th><th>Total consumido</th></tr></thead>
                        <tbody>
                        @forelse ($consumoRepuestos as $row)
                            <tr><td><code>{{ $row->numero_parte }}</code></td><td>{{ $row->descripcion }}</td><td>{{ $row->total_consumido }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-3">Sin movimientos de stock aún.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
