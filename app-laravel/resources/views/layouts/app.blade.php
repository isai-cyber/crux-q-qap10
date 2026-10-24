<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
@php
    $navItems = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
        ['route' => 'chat.index', 'label' => 'Chat con ORION', 'icon' => 'bi-chat-dots'],
        ['route' => 'tickets.index', 'label' => 'Tickets', 'icon' => 'bi-ticket-detailed'],
        ['route' => 'ordenes.index', 'label' => 'Órdenes de trabajo', 'icon' => 'bi-clipboard-check'],
        ['route' => 'activos.index', 'label' => 'Activos (CMDB)', 'icon' => 'bi-hdd-network'],
        ['route' => 'catalogo.index', 'label' => 'Catálogo', 'icon' => 'bi-boxes'],
        ['route' => 'runbooks.index', 'label' => 'Runbooks', 'icon' => 'bi-arrow-repeat'],
        ['route' => 'disenos.index', 'label' => 'Diseños técnicos IA', 'icon' => 'bi-magic'],
        ['route' => 'conocimiento.index', 'label' => 'Mapa de conocimiento 3D', 'icon' => 'bi-diagram-3'],
        ['route' => 'reportes.index', 'label' => 'Reportes', 'icon' => 'bi-bar-chart-line'],
    ];
    $navAdmin = [
        ['route' => 'usuarios.index', 'label' => 'Usuarios', 'icon' => 'bi-people'],
        ['route' => 'tokens.index', 'label' => 'Tokens de API', 'icon' => 'bi-key'],
        ['route' => 'mcp.index', 'label' => 'Integraciones MCP', 'icon' => 'bi-plug'],
    ];
@endphp
<div class="d-flex">
    <nav class="sidebar p-3 flex-shrink-0" style="width: 250px; min-height: 100vh;">
        <h5 class="mb-4 d-flex align-items-center gap-2"><i class="bi bi-cpu"></i> {{ config('app.name') }}</h5>
        <ul class="nav nav-pills flex-column gap-1">
            @foreach ($navItems as $item)
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2 {{ Route::has($item['route']) && request()->routeIs($item['route'].'*') ? 'active' : '' }}"
                       href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}">
                        <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
            @if (auth()->user()?->esAdmin())
                <li class="nav-item mt-3"><small class="text-uppercase text-secondary px-3">Administración</small></li>
                @foreach ($navAdmin as $item)
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 {{ Route::has($item['route']) && request()->routeIs($item['route'].'*') ? 'active' : '' }}"
                           href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}">
                            <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            @endif
        </ul>
        <hr>
        <div class="small d-flex align-items-center gap-2">
            <i class="bi bi-person-circle fs-5 text-secondary"></i>
            <span>{{ auth()->user()?->nombre }}<br><span class="badge bg-light text-dark text-uppercase">{{ auth()->user()?->rol }}</span></span>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button class="btn btn-outline-secondary btn-sm w-100" type="submit"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
        </form>
    </nav>
    <main class="flex-fill p-4" style="min-width: 0;">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
@yield('scripts')
</body>
</html>
