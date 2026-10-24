@extends('layouts.guest')

@section('title', 'Crear cuenta')

@section('content')
    <h4 class="mb-1 text-center">Crear tu cuenta</h4>
    <p class="text-secondary text-center small mb-3">Empieza gratis con tu organización</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('registro') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Nombre de tu organización</label>
            <input type="text" name="organizacion" class="form-control" value="{{ old('organizacion') }}" placeholder="Ej: Mantenimiento Industrial S.A." required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tu nombre</label>
            <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Crear cuenta</button>
    </form>
    <p class="text-center small text-secondary mt-3 mb-0">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>
    </p>
@endsection
