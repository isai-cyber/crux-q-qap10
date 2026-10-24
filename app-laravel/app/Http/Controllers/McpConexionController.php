<?php

namespace App\Http\Controllers;

use App\Models\ConexionMcp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Conexiones SALIENTES a servidores MCP de agentes externos: la app actúa
 * como cliente MCP (contraparte del servidor propio en /mcp). Al guardar una
 * conexión se hace un "tools/list" de prueba contra ella para validar que
 * responde antes de darla por buena ("plug and play").
 */
class McpConexionController extends Controller
{
    public function index()
    {
        $conexiones = ConexionMcp::orderByDesc('created_at')->get();

        return view('modulos.mcp.index', compact('conexiones'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'token' => ['nullable', 'string'],
        ]);

        $conexion = new ConexionMcp([
            'organizacion_id' => auth()->user()->organizacion_id,
            'nombre' => $data['nombre'],
            'url' => $data['url'],
            'activo' => true,
        ]);
        if (! empty($data['token'])) {
            $conexion->token = $data['token'];
        }
        $conexion->save();

        $this->verificar($conexion);

        return redirect()->route('mcp.index')->with('status', 'Conexión guardada.');
    }

    public function verificarAccion(ConexionMcp $conexion)
    {
        $this->verificar($conexion);

        return redirect()->route('mcp.index');
    }

    public function destroy(ConexionMcp $conexion)
    {
        $conexion->delete();

        return redirect()->route('mcp.index')->with('status', 'Conexión eliminada.');
    }

    /** Hace un tools/list de prueba para confirmar que el servidor MCP remoto responde. */
    private function verificar(ConexionMcp $conexion): void
    {
        try {
            $request = Http::timeout(10)->acceptJson();
            if ($conexion->token) {
                $request = $request->withToken($conexion->token);
            }

            $response = $request->post($conexion->url, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => [],
            ]);

            if ($response->successful() && $response->json('result.tools') !== null) {
                $conexion->update([
                    'ultima_verificacion' => now(),
                    'ultimo_estado' => 'conectado',
                    'ultimo_error' => null,
                ]);
            } else {
                $conexion->update([
                    'ultima_verificacion' => now(),
                    'ultimo_estado' => 'error',
                    'ultimo_error' => 'Respuesta inesperada (HTTP '.$response->status().')',
                ]);
            }
        } catch (\Throwable $e) {
            $conexion->update([
                'ultima_verificacion' => now(),
                'ultimo_estado' => 'error',
                'ultimo_error' => $e->getMessage(),
            ]);
        }
    }
}
