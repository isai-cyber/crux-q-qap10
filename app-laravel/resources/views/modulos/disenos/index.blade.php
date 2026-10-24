@extends('layouts.app')

@section('title', 'Diseños técnicos IA')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Diseños técnicos con IA</h2>
        <a href="{{ route('disenos.create') }}" class="btn btn-primary">+ Nuevo diseño</a>
    </div>
    <p class="text-secondary">Diagramas de control/cableado y documentos técnicos generados por IA a partir de una descripción.</p>

    <div class="row g-3">
        @forelse ($disenos as $d)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <span class="badge bg-{{ $d->tipo === 'diagrama' ? 'info' : 'secondary' }} text-dark mb-2">{{ $d->tipo }}</span>
                        <h5 class="card-title">{{ Str::limit($d->titulo, 60) }}</h5>
                        <p class="small text-secondary">Activo: {{ $d->activo->nombre ?? 'General' }} · {{ $d->created_at }}</p>
                        <a href="{{ route('disenos.show', $d) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-secondary">Aún no se ha generado ningún diseño técnico.</p>
        @endforelse
    </div>
@endsection
