<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = [
            'Motor' => ['motor', 'piston', 'cilindro', 'culata', 'ciguenal'],
            'Lubricación y filtros' => ['lubricacion', 'aceite', 'lubricante', 'filtro'],
            'Combustible y admisión' => ['combustible', 'inyector', 'bomba de combustible', 'carburador', 'admision'],
            'Refrigeración' => ['radiador', 'refrigerante', 'termostato', 'ventilador', 'bomba de agua'],
            'Transmisión y embrague' => ['transmision', 'caja de cambios', 'embrague', 'clutch', 'diferencial'],
            'Frenos' => ['freno', 'pastilla', 'disco de freno', 'tambor', 'zapata'],
            'Suspensión y dirección' => ['suspension', 'amortiguador', 'direccion', 'terminal', 'rotula'],
            'Sistema eléctrico y electrónico' => ['alternador', 'bateria', 'arnes', 'sensor', 'fusible', 'modulo', 'ecu'],
            'Iluminación' => ['iluminacion', 'faro', 'lampara', 'bombillo', 'foco', 'direccional'],
            'Neumáticos y ruedas' => ['neumatico', 'llanta', 'rueda', 'rin', 'aro'],
            'Sistema neumático auxiliar' => ['compresor de aire', 'valvula neumatica', 'manguera neumatica'],
            'Escape' => ['escape', 'silenciador', 'catalizador', 'mofle'],
            'Aire acondicionado y ventilación' => ['aire acondicionado', 'evaporador', 'compresor de aire acondicionado', 'ventilacion'],
            'Carrocería e interior' => ['carroceria', 'puerta', 'asiento', 'tablero', 'espejo', 'parabrisas'],
            'Seguridad y emergencia' => ['extintor', 'triangulo', 'botiquin', 'cinturon de seguridad', 'alarma'],
            'Consumibles de taller' => ['grasa', 'solvente', 'desengrasante', 'consumible'],
        ];

        foreach ($types as $name => $keywords) {
            if (DB::table('inventory_types')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('inventory_types')->insert([
                'name' => $name,
                'keywords' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep catalog entries and classifications intact on rollback.
    }
};
