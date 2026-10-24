<?php

namespace Database\Seeders;

use App\Models\BaseConocimientoChunk;
use Illuminate\Database\Seeder;

/**
 * Muestra real de contenido de los manuales BE2 (SINUMERIK) y SAT (SIMATIC
 * Automation Tool), extraída directamente de los PDF durante el análisis del
 * proyecto original (ver docs/ANALISIS.md). No es la ingesta completa de
 * ambos manuales (1451 + 187 páginas) — eso requiere generar embeddings
 * reales con AI_API_KEY y es trabajo aparte.
 */
class ConocimientoSeeder extends Seeder
{
    public function run(): void
    {
        BaseConocimientoChunk::create([
            'documento_origen' => 'SAT_UserGuide_es-ES.pdf',
            'capitulo' => '4. Operaciones de dispositivo',
            'texto' => 'SIMATIC Automation Tool permite: escanear una red, hacer ping a los dispositivos para '
                .'verificar la conexión, insertar uno o más dispositivos manualmente, confiar en CPU con '
                .'certificados para comunicación segura, identificar dispositivos, mostrar el diagnóstico de '
                .'dispositivos, leer los datos de servicio de un dispositivo, ajustar el estado operativo de una '
                .'CPU, ajustar la hora de una CPU, ajustar una dirección IP, ajustar un nombre PROFINET, '
                .'restablecer los parámetros de comunicación o la configuración de fábrica de un dispositivo, '
                .'hacer un borrado total en las CPU, formatear una Memory Card y actualizar el firmware de un '
                .'dispositivo.',
        ]);

        BaseConocimientoChunk::create([
            'documento_origen' => 'SAT_UserGuide_es-ES.pdf',
            'capitulo' => '3. Requisitos de configuración de STEP 7',
            'texto' => 'Para ajustar una dirección IP o un nombre PROFINET desde SIMATIC Automation Tool es '
                .'necesario cumplir requisitos de configuración de STEP 7 específicos para cada operación; el '
                .'manual detalla por separado los requisitos para ajustar dirección IP (sección 3.1) y para '
                .'ajustar nombre PROFINET (sección 3.2).',
        ]);

        BaseConocimientoChunk::create([
            'documento_origen' => 'BE2_es_es-ES.pdf',
            'capitulo' => 'Prefacio — Consignas básicas de seguridad',
            'texto' => 'PELIGRO significa que, si no se adoptan las medidas preventivas adecuadas, se producirá '
                .'la muerte o bien lesiones corporales graves. ADVERTENCIA significa que, si no se adoptan las '
                .'medidas preventivas adecuadas, puede producirse la muerte o bien lesiones corporales graves. '
                .'PRECAUCIÓN indica un riesgo menor si no se siguen las medidas preventivas indicadas.',
        ]);

        BaseConocimientoChunk::create([
            'documento_origen' => 'BE2_es_es-ES.pdf',
            'capitulo' => 'Índice general',
            'texto' => 'El manual de puesta en marcha de SINUMERIK 840D sl / 840DE sl cubre: SINUMERIK Operate '
                .'(IM9), Easy XML, SINUMERIK Integrate Access MyMachine/OPC UA, SINUMERIK Integrate Run '
                .'MyScreens (BE2), el sistema operativo NCU (IM7), y el software básico PCU (IM8 e IM10). Aplica '
                .'al control SINUMERIK 840D sl/840DE sl con software de CNC V4.7 SP1 y SINUMERIK Operate V4.7 SP1.',
        ]);
    }
}
