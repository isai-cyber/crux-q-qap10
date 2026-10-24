@extends('layouts.app')

@section('title', 'Órdenes de trabajo')

@section('content')
    <h2 class="mb-3">Órdenes de trabajo</h2>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Ticket</th><th>Técnico</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($ordenes as $o)
                    <tr>
                        <td>{{ $o->ticket->folio }} — {{ Str::limit($o->ticket->descripcion, 50) }}</td>
                        <td>{{ $o->tecnico->nombre ?? 'Sin asignar' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $o->estado }}</span></td>
                        <td><a href="{{ route('ordenes.show', $o) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-3">Sin órdenes de trabajo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $ordenes->links() }}</div>
@endsection
