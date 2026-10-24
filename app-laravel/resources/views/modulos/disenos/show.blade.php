@extends('layouts.app')

@section('title', $diseno->titulo)

@section('content')
    <h2 class="mb-1">{{ $diseno->titulo }}</h2>
    <p class="text-secondary">
        <span class="badge bg-{{ $diseno->tipo === 'diagrama' ? 'info' : 'secondary' }} text-dark">{{ $diseno->tipo }}</span>
        Activo: {{ $diseno->activo->nombre ?? 'General' }} · Generado: {{ $diseno->created_at }}
    </p>

    <div class="card mb-3">
        <div class="card-header">Solicitud original</div>
        <div class="card-body">{!! nl2br(e($diseno->descripcion_solicitud)) !!}</div>
    </div>

    <div class="card">
        <div class="card-header">Resultado</div>
        <div class="card-body">
            @if ($diseno->formato === 'svg')
                <img src="data:image/svg+xml;base64,{{ base64_encode($diseno->contenido) }}"
                     alt="Diagrama técnico generado por IA" class="img-fluid border rounded" style="max-width: 100%;">
            @else
                <pre class="border rounded p-3 bg-light" style="white-space: pre-wrap;">{{ $diseno->contenido }}</pre>
            @endif
        </div>
    </div>

    <a href="{{ route('disenos.index') }}" class="btn btn-outline-secondary mt-3">Volver</a>
@endsection
