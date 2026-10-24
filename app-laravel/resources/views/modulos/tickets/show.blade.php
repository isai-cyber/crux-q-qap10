@extends('layouts.app')

@section('title', $ticket->folio)

@section('content')
    <h2 class="mb-3">Ticket {{ $ticket->folio }}</h2>

    <div class="row g-3">
        <div class="col-md-7">
            <div class="card mb-3">
                <div class="card-header">Descripción del reporte</div>
                <div class="card-body">
                    <p>{!! nl2br(e($ticket->descripcion)) !!}</p>
                    <div class="small text-secondary">
                        Activo: {{ $ticket->activo->nombre ?? '—' }} ·
                        Canal: {{ $ticket->canal_origen }} ·
                        Apertura: {{ $ticket->fecha_apertura }}
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Clasificación por IA</div>
                <div class="card-body">
                    @if ($ticket->prioridad_ia)
                        <p>
                            Categoría sugerida: <strong>{{ $ticket->categoria_ia ?? '—' }}</strong><br>
                            Prioridad sugerida: <span class="badge badge-prioridad-{{ $ticket->prioridad_ia }}">{{ $ticket->prioridad_ia }}</span>
                        </p>
                    @else
                        <p class="text-secondary">Este ticket no fue clasificado por IA.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Estado del ticket</div>
                <div class="card-body">
                    <p>Estado actual: <span class="badge bg-light text-dark">{{ $ticket->estado }}</span></p>
                    <form method="POST" action="{{ route('tickets.estado', $ticket) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Nuevo estado</label>
                            <select name="estado" class="form-select">
                                @foreach (['abierto', 'en_progreso', 'esperando_repuesto', 'cerrado', 'cancelado'] as $estado)
                                    <option value="{{ $estado }}" @selected($ticket->estado === $estado)>{{ $estado }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Prioridad final</label>
                            <select name="prioridad_final" class="form-select">
                                <option value="">-- Mantener sugerida por IA --</option>
                                @foreach (['baja', 'media', 'alta', 'critica'] as $p)
                                    <option value="{{ $p }}" @selected($ticket->prioridad_final === $p)>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Actualizar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
