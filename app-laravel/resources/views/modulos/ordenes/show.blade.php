@extends('layouts.app')

@section('title', 'Orden de trabajo')

@section('content')
    <h2 class="mb-3">Orden de trabajo — {{ $orden->ticket->folio }}</h2>
    <p class="text-secondary">{{ $orden->ticket->descripcion }}</p>

    @if ($runbookSugerido)
        <div class="alert alert-info">
            <strong>Runbook sugerido automáticamente</strong> (síntoma similar a un caso ya resuelto):<br>
            <em>{{ $runbookSugerido->titulo }}</em> — {{ $runbookSugerido->solucion }}
            <div class="small text-secondary mt-1">
                {{ $runbookSugerido->veces_exitoso }} aplicaciones exitosas de {{ $runbookSugerido->veces_aplicado }}
                · {{ $runbookSugerido->validado ? 'Validado' : 'Aún sugerido' }}
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('ordenes.update', $orden) }}" class="card p-4" style="max-width: 640px;">
        @csrf
        <div class="mb-3">
            <label class="form-label">Técnico asignado</label>
            <select name="tecnico_id" class="form-select">
                <option value="">-- Sin asignar --</option>
                @foreach ($tecnicos as $t)
                    <option value="{{ $t->id }}" @selected($orden->tecnico_id === $t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Runbook aplicado</label>
            <select name="runbook_id" class="form-select">
                <option value="">-- Ninguno / solución nueva --</option>
                @foreach ($runbooksDisponibles as $r)
                    <option value="{{ $r->id }}" @selected($orden->runbook_id === $r->id)>{{ $r->titulo }}</option>
                @endforeach
            </select>
            <div class="form-text">Si no seleccionas ninguno y escribes una solución, se creará un runbook nuevo automáticamente al completar.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Diagnóstico</label>
            <textarea name="diagnostico" class="form-control" rows="3">{{ $orden->diagnostico }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Solución aplicada</label>
            <textarea name="solucion_aplicada" class="form-control" rows="3">{{ $orden->solucion_aplicada }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">¿La solución funcionó?</label>
            <select name="solucion_exitosa" class="form-select">
                <option value="" @selected(is_null($orden->solucion_exitosa))>-- Aún sin confirmar --</option>
                <option value="1" @selected($orden->solucion_exitosa === true)>Sí, resolvió el problema</option>
                <option value="0" @selected($orden->solucion_exitosa === false)>No, el problema persistió</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Tiempo invertido (minutos)</label>
            <input type="number" name="tiempo_invertido_minutos" class="form-control" min="0" value="{{ $orden->tiempo_invertido_minutos }}">
        </div>

        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                @foreach (['pendiente', 'en_progreso', 'completada'] as $estado)
                    <option value="{{ $estado }}" @selected($orden->estado === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
            <div class="form-text">Al marcar "completada" el ticket se cierra y se aplica la retroalimentación al runbook.</div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
@endsection
