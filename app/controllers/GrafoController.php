<?php

declare(strict_types=1);

/**
 * Construye el grafo de conocimiento que conecta lo que la capa de IA usa
 * para razonar: activos, tickets, runbooks (autoevolutivos), diseños
 * técnicos generados y los documentos fuente de la base de conocimiento.
 * Todo se filtra por organización, salvo los documentos fuente (globales).
 */
final class GrafoController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $grafo = $this->construirGrafo($org);
        require dirname(__DIR__, 2) . '/views/modulos/grafo/index.php';
    }

    private function construirGrafo(int $org): array
    {
        $db = Database::connection();
        $nodes = [];
        $links = [];

        $activosStmt = $db->prepare('SELECT id, nombre, estado FROM activos WHERE organizacion_id = :org');
        $activosStmt->execute(['org' => $org]);
        foreach ($activosStmt->fetchAll() as $a) {
            $nodes[] = [
                'id' => "activo-{$a['id']}",
                'label' => $a['nombre'],
                'tipo' => 'activo',
                'val' => 6,
            ];
        }

        $ticketsStmt = $db->prepare(
            "SELECT id, folio, activo_id, prioridad_final, prioridad_ia, estado
             FROM tickets WHERE organizacion_id = :org ORDER BY fecha_apertura DESC LIMIT 60"
        );
        $ticketsStmt->execute(['org' => $org]);
        foreach ($ticketsStmt->fetchAll() as $t) {
            $nodes[] = [
                'id' => "ticket-{$t['id']}",
                'label' => $t['folio'],
                'tipo' => 'ticket',
                'val' => 3,
            ];
            if ($t['activo_id']) {
                $links[] = ['source' => "ticket-{$t['id']}", 'target' => "activo-{$t['activo_id']}", 'tipo' => 'reporta_falla_en'];
            }
        }

        $runbooksStmt = $db->prepare(
            'SELECT id, titulo, activo_id, validado, vigente, veces_aplicado, veces_exitoso FROM runbooks WHERE organizacion_id = :org'
        );
        $runbooksStmt->execute(['org' => $org]);
        foreach ($runbooksStmt->fetchAll() as $r) {
            $nodes[] = [
                'id' => "runbook-{$r['id']}",
                'label' => $r['titulo'],
                'tipo' => $r['vigente'] ? ($r['validado'] ? 'runbook_validado' : 'runbook_sugerido') : 'runbook_retirado',
                'val' => 4 + min((int) $r['veces_aplicado'], 10),
            ];
            if ($r['activo_id']) {
                $links[] = ['source' => "runbook-{$r['id']}", 'target' => "activo-{$r['activo_id']}", 'tipo' => 'aplica_a'];
            }
        }

        $aplicStmt = $db->prepare(
            'SELECT ra.runbook_id, ra.ticket_id, ra.resultado
             FROM runbook_aplicaciones ra
             JOIN runbooks r ON r.id = ra.runbook_id
             WHERE r.organizacion_id = :org'
        );
        $aplicStmt->execute(['org' => $org]);
        foreach ($aplicStmt->fetchAll() as $ap) {
            $links[] = [
                'source' => "runbook-{$ap['runbook_id']}",
                'target' => "ticket-{$ap['ticket_id']}",
                'tipo' => $ap['resultado'] === 'exitoso' ? 'resolvio' : 'no_resolvio',
            ];
        }

        $disenosStmt = $db->prepare('SELECT id, titulo, tipo, activo_id FROM disenos_tecnicos WHERE organizacion_id = :org');
        $disenosStmt->execute(['org' => $org]);
        foreach ($disenosStmt->fetchAll() as $d) {
            $nodes[] = [
                'id' => "diseno-{$d['id']}",
                'label' => $d['titulo'],
                'tipo' => 'diseno',
                'val' => 3,
            ];
            if ($d['activo_id']) {
                $links[] = ['source' => "diseno-{$d['id']}", 'target' => "activo-{$d['activo_id']}", 'tipo' => 'documenta'];
            }
        }

        // Documentos fuente de la base de conocimiento: GLOBALES (compartidos).
        $documentosFuente = $db->query(
            'SELECT documento_origen, COUNT(*) AS chunks FROM base_conocimiento_chunks GROUP BY documento_origen'
        )->fetchAll();
        foreach ($documentosFuente as $doc) {
            $nodeId = 'doc-' . md5($doc['documento_origen']);
            $nodes[] = [
                'id' => $nodeId,
                'label' => $doc['documento_origen'],
                'tipo' => 'fuente_conocimiento',
                'val' => 5,
            ];
        }

        return ['nodes' => $nodes, 'links' => $links];
    }
}
