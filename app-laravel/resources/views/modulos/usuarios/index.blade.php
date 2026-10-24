@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <h2 class="mb-3">Usuarios</h2>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th></tr></thead>
                        <tbody>
                        @foreach ($usuarios as $u)
                            <tr>
                                <td>{{ $u->nombre }}</td>
                                <td>{{ $u->email }}</td>
                                <td><span class="badge bg-light text-dark">{{ $u->rol }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('usuarios.update', $u) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="activo" value="0">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="activo" value="1" onchange="this.form.submit()" {{ $u->activo ? 'checked' : '' }}>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Nuevo usuario</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('usuarios.store') }}">
                        @csrf
                        <div class="mb-2"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">Contraseña</label><input type="password" name="password" class="form-control" minlength="8" required></div>
                        <div class="mb-2">
                            <label class="form-label">Rol</label>
                            <select name="rol" class="form-select">
                                <option value="tecnico">Técnico</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Planta</label>
                            <select name="planta_id" class="form-select">
                                <option value="">-- Sin asignar --</option>
                                @foreach ($plantas as $p)
                                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Crear usuario</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
