<?php
$pageTitle = ChatController::NOMBRE_ASISTENTE;
require __DIR__ . '/../../layouts/header.php';
$org = Auth::user()['organizacion_id'];
$stmt = Database::connection()->prepare('SELECT id, nombre FROM activos WHERE organizacion_id = :org ORDER BY nombre');
$stmt->execute(['org' => $org]);
$activos = $stmt->fetchAll();
$nombreAsistente = ChatController::NOMBRE_ASISTENTE;
$iaConfigurada = (new AIService($org))->isConfigured();

// Consumo de IA de la organización (tokens reportados por el proveedor).
$stmtUso = Database::connection()->prepare(
    'SELECT COUNT(*) AS llamadas,
            COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
            COALESCE(SUM(completion_tokens), 0) AS completion_tokens
     FROM logs_ia WHERE organizacion_id = :org'
);
$stmtUso->execute(['org' => $org]);
$usoIa = $stmtUso->fetch();
?>

<?php if (!$iaConfigurada): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" style="max-width: 1100px;">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong><?= e($nombreAsistente) ?> aún no está configurado.</strong>
            Para activar sus respuestas con IA, define <code>AI_API_KEY</code> (y opcionalmente
            <code>AI_PROVIDER</code> / <code>AI_MODEL</code>) en el archivo <code>.env</code> y reinicia.
            Mientras tanto, los reportes de falla se clasifican con la heurística de respaldo.
        </div>
    </div>
<?php endif; ?>

<div class="chat-shell card">
    <!-- Encabezado del chat -->
    <div class="chat-header">
        <div class="d-flex align-items-center gap-3">
            <span class="chat-avatar"><i class="bi bi-stars"></i></span>
            <div>
                <div class="chat-nombre"><?= e($nombreAsistente) ?></div>
                <div class="chat-estado">
                    <span class="chat-punto <?= $iaConfigurada ? '' : 'inactivo' ?>"></span>
                    <?= $iaConfigurada ? 'En línea · IA activa' : 'Sin IA configurada · modo heurístico' ?>
                </div>
            </div>
        </div>
        <div class="chat-stats" title="Consumo de IA de tu organización (tokens reportados por el proveedor)">
            <span class="chat-stat"><i class="bi bi-lightning-charge"></i> <?= (int) $usoIa['llamadas'] ?> llamadas</span>
            <span class="chat-stat"><i class="bi bi-box-arrow-in-right"></i> <?= number_format((int) $usoIa['prompt_tokens']) ?> tokens in</span>
            <span class="chat-stat"><i class="bi bi-box-arrow-up-right"></i> <?= number_format((int) $usoIa['completion_tokens']) ?> tokens out</span>
        </div>
    </div>

    <!-- Mensajes -->
    <div id="chat-mensajes" class="chat-mensajes">
        <?php foreach ($historial as $m): ?>
            <div class="chat-fila chat-<?= e($m['rol']) ?>">
                <span class="chat-avatar-mini <?= $m['rol'] === 'asistente' ? '' : 'propio' ?>">
                    <i class="bi <?= $m['rol'] === 'asistente' ? 'bi-stars' : 'bi-person-fill' ?>"></i>
                </span>
                <div>
                    <div class="chat-burbuja chat-<?= e($m['rol']) ?>">
                        <div class="chat-texto"><?= nl2br(e($m['contenido'])) ?></div>
                        <?php if ($m['folio']): ?>
                            <a href="<?= base_url('/tickets/' . $m['ticket_id']) ?>" class="chat-ticket-link"><i class="bi bi-ticket-detailed"></i> <?= e($m['folio']) ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="chat-hora"><?= e(date('H:i', strtotime($m['created_at']))) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$historial): ?>
            <div class="chat-vacio">
                <span class="chat-avatar"><i class="bi bi-stars"></i></span>
                <h5>Hola, soy <?= e($nombreAsistente) ?></h5>
                <p>Descríbeme una falla como si le escribieras a un compañero y registraré el ticket con triage automático.
                   También puedes preguntarme por los manuales técnicos.</p>
                <div class="chat-sugerencias">
                    <button type="button" class="chat-sugerencia">El contactor de la línea 2 no arranca</button>
                    <button type="button" class="chat-sugerencia">¿Qué dice el manual SINUMERIK sobre la alarma 25000?</button>
                </div>
            </div>
        <?php endif; ?>
        <div id="chat-escribiendo" class="chat-fila chat-asistente d-none">
            <span class="chat-avatar-mini"><i class="bi bi-stars"></i></span>
            <div class="chat-escribiendo"><span></span><span></span><span></span></div>
        </div>
    </div>

    <!-- Composer -->
    <form id="chat-form" class="chat-composer">
        <?= csrf_field() ?>
        <div class="chat-pill">
            <select name="activo_id" class="form-select chat-activo" aria-label="Activo relacionado">
                <option value="">⚙ Activo (opcional)</option>
                <?php foreach ($activos as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"><?= e($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="mensaje" id="chat-input" class="form-control chat-input" placeholder="Escribe un mensaje a <?= e($nombreAsistente) ?>…" autocomplete="off" required>
            <button type="submit" class="btn btn-primary chat-enviar" aria-label="Enviar">
                <i class="bi bi-send"></i>
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('chat-form');
    var lista = document.getElementById('chat-mensajes');
    var input = document.getElementById('chat-input');
    var escribiendo = document.getElementById('chat-escribiendo');

    function scrollAbajo() {
        lista.scrollTop = lista.scrollHeight;
    }
    scrollAbajo();

    // Sugerencias clicables (solo cuando el chat está vacío).
    document.querySelectorAll('.chat-sugerencia').forEach(function (btn) {
        btn.addEventListener('click', function () {
            input.value = btn.textContent.trim();
            input.focus();
        });
    });

    function agregarBurbuja(rol, texto, ticketFolio, ticketId) {
        var vacio = lista.querySelector('.chat-vacio');
        if (vacio) vacio.remove();

        var hora = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

        var fila = document.createElement('div');
        fila.className = 'chat-fila chat-' + rol;

        var avatar = document.createElement('span');
        avatar.className = 'chat-avatar-mini' + (rol === 'asistente' ? '' : ' propio');
        avatar.innerHTML = '<i class="bi ' + (rol === 'asistente' ? 'bi-stars' : 'bi-person-fill') + '"></i>';

        var contenedor = document.createElement('div');

        var div = document.createElement('div');
        div.className = 'chat-burbuja chat-' + rol;

        var textoDiv = document.createElement('div');
        textoDiv.className = 'chat-texto';
        textoDiv.textContent = texto;
        div.appendChild(textoDiv);

        if (ticketFolio) {
            var link = document.createElement('a');
            link.className = 'chat-ticket-link';
            link.href = '<?= base_url('/tickets/') ?>' + ticketId;
            link.innerHTML = '<i class="bi bi-ticket-detailed"></i> ' + ticketFolio;
            div.appendChild(link);
        }

        var horaDiv = document.createElement('div');
        horaDiv.className = 'chat-hora';
        horaDiv.textContent = hora;

        contenedor.appendChild(div);
        contenedor.appendChild(horaDiv);
        fila.appendChild(avatar);
        fila.appendChild(contenedor);
        lista.insertBefore(fila, escribiendo.parentElement === lista ? escribiendo : null);
        scrollAbajo();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var mensaje = input.value.trim();
        if (!mensaje) return;

        var datos = new URLSearchParams(new FormData(form));

        agregarBurbuja('usuario', mensaje, null, null);
        input.value = '';
        input.disabled = true;
        escribiendo.classList.remove('d-none');
        scrollAbajo();

        fetch('<?= base_url('/chat/enviar') ?>', {
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
                escribiendo.classList.add('d-none');
                input.disabled = false;
                input.focus();
                scrollAbajo();
            });
    });
})();
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
