@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h4 class="mb-3 text-center">Iniciar sesión</h4>

    @error('email')
        <div class="alert alert-danger py-2">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>
    <p class="text-center small text-secondary mt-3 mb-0">
        ¿Aún no tienes cuenta? <a href="{{ route('registro') }}">Crea tu organización</a>
    </p>
@endsection
