@extends('layouts.app')

@section('title', 'Integraciones MCP')

@section('content')
    <h2 class="mb-3">Integraciones MCP</h2>
    <p class="text-secondary">
        Conecta esta plataforma a otros agentes de automatización que hablen el protocolo MCP (plug and play):
        agrega la URL de su servidor MCP y, opcionalmente, un token. Al guardar se hace una prueba de conexión
        automática (<code>tools/list</code>). Esta pantalla es la contraparte cliente del servidor MCP propio
        expuesto en <code>/mcp</code> (ver <code>docs/MCP.md</code>).
    </p>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Nombre</th><th>URL</th><th>Estado</th><th>Última verificación</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($conexiones as $c)
                            <tr>
                                <td>{{ $c->nombre }}</td>
                                <td class="text-truncate" style="max-width: 220px;"><small>{{ $c->url }}</small></td>
                                <td>
                                    @if ($c->ultimo_estado === 'conectado')
                                        <span class="badge bg-success">Conectado</span>
                                    @elseif ($c->ultimo_estado === 'error')
                                        <span class="badge bg-danger" title="{{ $c->ultimo_error }}">Error</span>
                                    @else
                                        <span class="badge bg-secondary">Sin verificar</span>
                                    @endif
                                </td>
                                <td><small>{{ $c->ultima_verificacion ?? '—' }}</small></td>
                                <td class="d-flex gap-1">
                                    <form method="POST" action="{{ route('mcp.verificar', $c) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Probar</button>
                                    </form>
                                    <form method="POST" action="{{ route('mcp.destroy', $c) }}" data-confirm="¿Eliminar esta conexión?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-3">Sin conexiones configuradas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Nueva conexión</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('mcp.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Agente externo" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">URL del servidor MCP</label>
                            <input type="url" name="url" class="form-control" placeholder="https://agente-externo.com/mcp" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Token (opcional)</label>
                            <input type="text" name="token" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Guardar y probar conexión</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
