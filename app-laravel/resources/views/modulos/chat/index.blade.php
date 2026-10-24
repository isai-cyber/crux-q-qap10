@extends('layouts.app')

@section('title', 'Chat con '.\App\Http\Controllers\ChatController::NOMBRE_ASISTENTE)

@php $nombreAsistente = \App\Http\Controllers\ChatController::NOMBRE_ASISTENTE; @endphp

@section('content')
    <h2 class="mb-1">Chat con {{ $nombreAsistente }}</h2>
    <p class="text-secondary">
        {{ $nombreAsistente }} es el asistente de automatización industrial de esta plataforma. Describe una falla
        como si le escribieras a un compañero, pregúntale sobre los manuales (SINUMERIK, SIMATIC Automation Tool), o
        — si eres admin — pídele que dé de alta a un nuevo técnico o usuario.
    </p>

    <div class="card" style="max-width: 760px;">
        <div id="chat-mensajes" class="chat-mensajes p-3">
            @forelse ($historial as $m)
                <div class="chat-burbuja chat-{{ $m->rol }}">
                    <div class="chat-texto">{!! nl2br(e($m->contenido)) !!}</div>
                    @if ($m->ticket)
                        <a href="{{ route('tickets.show', $m->ticket) }}" class="chat-ticket-link">{{ $m->ticket->folio }}</a>
                    @endif
                </div>
            @empty
                <p class="text-secondary text-center">Aún no hay mensajes. Cuéntale a {{ $nombreAsistente }} qué está fallando.</p>
            @endforelse
        </div>

        <form id="chat-form" class="border-top p-3 d-flex gap-2">
            @csrf
            <select name="activo_id" class="form-select" style="max-width: 220px;">
                <option value="">Activo (opcional)</option>
                @foreach ($activos as $a)
                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                @endforeach
            </select>
            <input type="text" name="mensaje" id="chat-input" class="form-control" placeholder="Ej: el contactor de la línea 2 no arranca" autocomplete="off" required>
            <button type="submit" class="btn btn-primary">Enviar</button>
        </form>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('chat-form');
    var lista = document.getElementById('chat-mensajes');
    var input = document.getElementById('chat-input');

    function agregarBurbuja(rol, texto, ticketFolio, ticketId) {
        var vacio = lista.querySelector('p.text-secondary');
        if (vacio) vacio.remove();

        var div = document.createElement('div');
        div.className = 'chat-burbuja chat-' + rol;

        var textoDiv = document.createElement('div');
        textoDiv.className = 'chat-texto';
        textoDiv.textContent = texto;
        div.appendChild(textoDiv);

        if (ticketFolio) {
            var link = document.createElement('a');
            link.className = 'chat-ticket-link';
            link.href = '{{ url('/tickets') }}/' + ticketId;
            link.textContent = ticketFolio;
            div.appendChild(link);
        }

        lista.appendChild(div);
        lista.scrollTop = lista.scrollHeight;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var mensaje = input.value.trim();
        if (!mensaje) return;

        var datos = new URLSearchParams(new FormData(form));

        agregarBurbuja('usuario', mensaje, null, null);
        input.value = '';
        input.disabled = true;

        fetch('{{ route('chat.enviar') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: datos.toString()
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    agregarBurbuja('asistente', 'Error: ' + data.error, null, null);
                    return;
                }
                var ticket = data.ticket || null;
                agregarBurbuja('asistente', data.respuesta, ticket ? ticket.folio : null, ticket ? ticket.id : null);
            })
            .catch(function () {
                agregarBurbuja('asistente', 'No se pudo enviar el mensaje. Intenta de nuevo.', null, null);
            })
            .finally(function () {
                input.disabled = false;
                input.focus();
            });
    });
})();
</script>
@endsection
