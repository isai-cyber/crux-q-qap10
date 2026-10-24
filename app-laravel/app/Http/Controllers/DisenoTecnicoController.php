<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\DisenoTecnico;
use App\Models\ProductoCatalogo;
use App\Services\AIService;
use Illuminate\Http\Request;

class DisenoTecnicoController extends Controller
{
    public function index()
    {
        $disenos = DisenoTecnico::with('activo')->orderByDesc('created_at')->get();

        return view('modulos.disenos.index', compact('disenos'));
    }

    public function create()
    {
        $activos = Activo::orderBy('nombre')->get(['id', 'nombre']);
        $productos = ProductoCatalogo::orderBy('numero_parte')->get(['id', 'numero_parte', 'descripcion']);

        return view('modulos.disenos.form', compact('activos', 'productos'));
    }

    public function store(Request $request, AIService $ai)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:diagrama,documento'],
            'descripcion_solicitud' => ['required', 'string'],
            'activo_id' => ['nullable', 'exists:activos,id'],
            'producto_ids' => ['nullable', 'array'],
        ]);

        $activo = $data['activo_id'] ?? null ? Activo::find($data['activo_id']) : null;

        if ($data['tipo'] === 'diagrama') {
            $productos = ! empty($data['producto_ids'])
                ? ProductoCatalogo::whereIn('id', $data['producto_ids'])->get(['numero_parte', 'descripcion'])->toArray()
                : [];
            $contenido = $this->sanitizarSvg($ai->generateDiagram($data['descripcion_solicitud'], $productos));
            $formato = 'svg';
        } else {
            $contexto = $activo ? ['Activo' => $activo->nombre, 'Planta' => $activo->planta->nombre] : [];
            $contenido = $ai->generateTechDoc($data['descripcion_solicitud'], $contexto);
            $formato = 'markdown';
        }

        $diseno = DisenoTecnico::create([
            'organizacion_id' => auth()->user()->organizacion_id,
            'tipo' => $data['tipo'],
            'titulo' => \Illuminate\Support\Str::limit($data['descripcion_solicitud'], 100),
            'descripcion_solicitud' => $data['descripcion_solicitud'],
            'contenido' => $contenido,
            'formato' => $formato,
            'activo_id' => $activo?->id,
            'usuario_id' => auth()->id(),
        ]);

        return redirect()->route('disenos.show', $diseno);
    }

    public function show(DisenoTecnico $diseno)
    {
        return view('modulos.disenos.show', compact('diseno'));
    }

    /**
     * Defensa en profundidad: el SVG lo produce un LLM a partir de texto
     * libre del usuario; se muestra como <img> con data URI (los navegadores
     * no ejecutan <script> dentro de SVG en contexto <img>), pero igual se
     * elimina cualquier vector de script por si se reutiliza el contenido.
     */
    private function sanitizarSvg(string $svg): string
    {
        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $svg) ?? $svg;
        $svg = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $svg) ?? $svg;

        return preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $svg) ?? $svg;
    }
}
