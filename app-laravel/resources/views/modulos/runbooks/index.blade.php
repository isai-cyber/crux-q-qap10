@extends('layouts.app')

@section('title', 'Runbooks')

@section('content')
    <h2 class="mb-3">Runbooks (procedimientos de solución autoevolutivos)</h2>
    <p class="text-secondary">
        Se generan solos a partir de tickets resueltos. Cada vez que un técnico marca si la solución funcionó, el
        runbook ajusta su tasa de éxito: con 3+ aplicaciones y ≥70% de éxito se <strong>valida</strong> automáticamente;
        con ≥50% de fallo se <strong>retira</strong> y deja de sugerirse.
    </p>

    <div class="row g-3">
        @forelse ($runbooks as $r)
            @php $tasa = $r->veces_aplicado > 0 ? round($r->veces_exitoso / $r->veces_aplicado * 100) : null; @endphp
            <div class="col-md-6">
                <div class="card h-100 {{ ! $r->vigente ? 'border-danger' : '' }}">
                    <div class="card-body">
                        <h5 class="card-title">{{ $r->titulo }}</h5>
                        <p class="small text-secondary mb-1">Activo: {{ $r->activo->nombre ?? 'General' }}</p>
                        <p><strong>Síntoma:</strong> {{ $r->sintoma }}</p>
                        <p><strong>Solución:</strong> {{ $r->solucion }}</p>
                        <span class="badge {{ $r->validado ? 'bg-success' : 'bg-secondary' }}">{{ $r->validado ? 'Validado' : 'Sugerido' }}</span>
                        @if (! $r->vigente)<span class="badge bg-danger">Retirado automáticamente</span>@endif
                        <span class="badge bg-light text-dark">{{ $r->generado_por }}</span>
                        <span class="badge bg-light text-dark">{{ $r->veces_aplicado }} aplicaciones{{ $tasa !== null ? " · {$tasa}% éxito" : '' }}</span>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-secondary">Aún no hay runbooks registrados. Se crean automáticamente al completar órdenes de trabajo.</p>
        @endforelse
    </div>
@endsection
