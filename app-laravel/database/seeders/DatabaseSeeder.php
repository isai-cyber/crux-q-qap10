<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use App\Models\Organizacion;
use App\Models\Planta;
use App\Models\ProductoCatalogo;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organizacion = Organizacion::create([
            'nombre' => 'Organización Demo',
            'slug' => 'demo',
            'plan' => 'pro',
        ]);

        $planta = Planta::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Planta Demo',
            'ubicacion' => 'Sin especificar',
        ]);

        // Password: CambiaEstaClave123! — cambiar en el primer inicio de sesión.
        Usuario::create([
            'organizacion_id' => $organizacion->id,
            'nombre' => 'Administrador',
            'email' => 'admin@example.com',
            'password_hash' => bcrypt('CambiaEstaClave123!'),
            'rol' => 'admin',
            'planta_id' => $planta->id,
            'activo' => true,
        ]);

        // Catálogo Siemens: GLOBAL, no lleva organizacion_id.
        $categorias = collect([
            ['nombre' => 'SIRIUS Control', 'descripcion' => 'Arrancadores, contactores, relés de control'],
            ['nombre' => 'IEC Power Control', 'descripcion' => 'Guardamotores y accesorios IEC'],
            ['nombre' => 'SIRIUS Hybrid', 'descripcion' => 'Arrancadores híbridos y motor starters'],
            ['nombre' => 'Solid State Control', 'descripcion' => 'Relés de estado sólido y soft starters'],
        ])->map(fn ($c) => CategoriaProducto::create($c));

        ProductoCatalogo::create([
            'numero_parte' => '3RT2015-1BB41',
            'categoria_id' => $categorias[0]->id,
            'descripcion' => 'Contactor SIRIUS, 3 polos, 7A, bobina 24V DC',
            'precio_lista' => 45.00,
            'vigencia_catalogo' => 'enero 2026',
        ]);
        ProductoCatalogo::create([
            'numero_parte' => '3RV2011-1AA10',
            'categoria_id' => $categorias[1]->id,
            'descripcion' => 'Guardamotor SIRIUS, rango 1-1.6A',
            'precio_lista' => 62.50,
            'vigencia_catalogo' => 'enero 2026',
        ]);
        ProductoCatalogo::create([
            'numero_parte' => '3RW3014-1BB14',
            'categoria_id' => $categorias[3]->id,
            'descripcion' => 'Arrancador suave SIRIUS, 5.5kW, 400V',
            'precio_lista' => 210.00,
            'vigencia_catalogo' => 'enero 2026',
        ]);

        $this->call(ConocimientoSeeder::class);
    }
}
