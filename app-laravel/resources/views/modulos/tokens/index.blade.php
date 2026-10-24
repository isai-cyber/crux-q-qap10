@extends('layouts.app')

@section('title', 'Tokens de API')

@section('content')
    <h2 class="mb-3">Tokens de API (integración con agentes)</h2>
    <p class="text-secondary">
        Genera un token para que un agente de automatización externo pueda consumir la API REST del sistema
        (crear tickets, consultar activos/catálogo). Ver <code>docs/API.md</code>.
    </p>

    @if ($tokenPlano)
        <div class="alert alert-warning">
            <strong>Copia este token ahora, no se volverá a mostrar:</strong>
            <div class="font-monospace mt-1">{{ $tokenPlano }}</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Nombre</th><th>Permisos</th><th>Estado</th><th>Último uso</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($tokens as $t)
                            <tr>
                                <td>{{ $t->nombre }}</td>
                                <td>@foreach ($t->permisos as $p)<span class="badge bg-light text-dark">{{ $p }}</span>@endforeach</td>
                                <td>{!! $t->activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Revocado</span>' !!}</td>
                                <td>{{ $t->ultimo_uso ?? 'Nunca' }}</td>
                                <td>
                                    @if ($t->activo)
                                        <form method="POST" action="{{ route('tokens.revoke', $t) }}" data-confirm="¿Revocar este token?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Revocar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-3">Sin tokens generados.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Nuevo token</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('tokens.store') }}">
                        @csrf
                        <div class="mb-2"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" placeholder="Agente de campo - WhatsApp" required></div>
                        <div class="mb-3">
                            <label class="form-label">Permisos</label>
                            @foreach (['crear_ticket' => 'Crear tickets', 'consultar_activos' => 'Consultar activos', 'crear_activo' => 'Crear activos', 'consultar_catalogo' => 'Consultar catálogo', 'consultar_alertas' => 'Consultar alertas', 'crear_usuario' => 'Crear usuarios/técnicos'] as $key => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="{{ $key }}" id="perm_{{ $key }}">
                                    <label class="form-check-label" for="perm_{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Generar token</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
