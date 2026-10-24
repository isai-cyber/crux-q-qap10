@extends('layouts.app')

@section('title', 'Mapa de conocimiento 3D')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">Mapa de conocimiento 3D</h2>
            <p class="text-secondary mb-0">
                Cómo se conectan activos, tickets, runbooks (autoevolutivos), diseños técnicos
                y documentos fuente dentro del sistema. Arrastra para rotar, scroll para zoom.
            </p>
        </div>
    </div>

    <div class="graph-legend mb-3">
        <span class="legend-item"><i style="background:#3b82f6"></i> Activo</span>
        <span class="legend-item"><i style="background:#ec4899"></i> Ticket</span>
        <span class="legend-item"><i style="background:#facc15"></i> Runbook validado</span>
        <span class="legend-item"><i style="background:#fb923c"></i> Runbook sugerido</span>
        <span class="legend-item"><i style="background:#64748b"></i> Runbook retirado</span>
        <span class="legend-item"><i style="background:#a855f7"></i> Diseño técnico</span>
        <span class="legend-item"><i style="background:#22d3ee"></i> Documento fuente</span>
    </div>

    <div id="graph3d" class="graph-canvas"></div>
@endsection

@section('scripts')
<script type="module">
import * as THREE from 'https://esm.sh/three@0.180.0';
import ForceGraph3D from 'https://esm.sh/3d-force-graph@1.80.0?deps=three@0.180.0';

(function () {
    var data = @json($grafo);

    var colores = {
        activo: '#3b82f6', ticket: '#ec4899', runbook_validado: '#facc15',
        runbook_sugerido: '#fb923c', runbook_retirado: '#64748b', diseno: '#a855f7',
        fuente_conocimiento: '#22d3ee'
    };

    var elem = document.getElementById('graph3d');
    if (!data.nodes.length) {
        elem.innerHTML = '<p class="text-secondary text-center pt-5">Aún no hay suficientes datos para construir el grafo.</p>';
        return;
    }

    var texturaCache = {};
    function texturaGlow(colorHex) {
        if (texturaCache[colorHex]) return texturaCache[colorHex];
        var size = 128;
        var canvas = document.createElement('canvas');
        canvas.width = size; canvas.height = size;
        var ctx = canvas.getContext('2d');
        var gradiente = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
        gradiente.addColorStop(0, colorHex + 'ff');
        gradiente.addColorStop(0.35, colorHex + 'aa');
        gradiente.addColorStop(0.7, colorHex + '30');
        gradiente.addColorStop(1, colorHex + '00');
        ctx.fillStyle = gradiente;
        ctx.fillRect(0, 0, size, size);
        var textura = new THREE.CanvasTexture(canvas);
        texturaCache[colorHex] = textura;
        return textura;
    }

    var Graph = ForceGraph3D()(elem)
        .graphData(data)
        .nodeId('id')
        .nodeLabel(function (n) { return n.label; })
        .nodeVal('val')
        .nodeThreeObject(function (n) {
            var color = colores[n.tipo] || '#94a3b8';
            var base = Math.cbrt(n.val || 4);
            var grupo = new THREE.Group();

            var nucleo = new THREE.Mesh(new THREE.SphereGeometry(base * 1.3, 20, 20), new THREE.MeshBasicMaterial({ color: color }));
            grupo.add(nucleo);

            var haloInterior = new THREE.Sprite(new THREE.SpriteMaterial({ map: texturaGlow(color), transparent: true, opacity: 0.85, depthWrite: false }));
            var escalaInterior = base * 5;
            haloInterior.scale.set(escalaInterior, escalaInterior, 1);
            grupo.add(haloInterior);

            var haloExterior = new THREE.Sprite(new THREE.SpriteMaterial({ map: texturaGlow(color), transparent: true, opacity: 0.35, depthWrite: false }));
            var escalaExterior = base * 10;
            haloExterior.scale.set(escalaExterior, escalaExterior, 1);
            grupo.add(haloExterior);

            return grupo;
        })
        .nodeThreeObjectExtend(false)
        .linkLabel(function (l) { return l.tipo; })
        .linkColor(function (l) { return l.tipo === 'no_resolvio' ? '#ef4444' : 'rgba(30,41,59,0.22)'; })
        .linkOpacity(0.65)
        .linkWidth(1.2)
        .linkDirectionalParticles(2)
        .linkDirectionalParticleWidth(2)
        .linkDirectionalParticleColor(function () { return '#6366f1'; })
        .backgroundColor('#ffffff');

    window.addEventListener('resize', function () {
        Graph.width(elem.clientWidth).height(elem.clientHeight);
    });
})();
</script>
@endsection
