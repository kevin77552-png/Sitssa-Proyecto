<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class FleetUnitsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['ABC101', '12 MTS', 'ZK6118HGA', 'activa'],
            ['ABC102', '12 MTS', 'ZK6118HGA', 'activa'],
            ['ABC103', '12 MTS', 'ZK6896HGA', 'activa'],
            ['ABC104', '12 MTS', 'ZK6896HGA', 'activa'],
            ['ABC105', '10 MTS', 'ZK6122HO', 'activa'],
            ['ABC106', '10 MTS', 'ZK6122HO', 'activa'],
            ['ABC107', '8 MTS', 'ZK6831HE', 'activa'],
            ['ABC108', '8 MTS', 'ZK6831HE', 'activa'],
            ['ABC109', '7 MTS', 'ZK6100NGA9', 'activa'],
            ['ABC110', 'INTERURBANO', 'ZK6146H', 'activa'],
            ['ABC111', 'INTERURBANO', 'ZK6146H', 'activa'],
            ['ABC112', 'INTERURBANO', 'ZK6129H', 'activa'],
            ['ABC113', 'JAC', 'JAC', 'activa'],
            ['ABC114', 'JAC', 'JAC', 'activa'],
            ['ABC115', '12 MTS', 'ZK6118HGA', 'activa'],
            ['ABC116', '8 MTS', 'ZK6831HE', 'inactiva'],
        ];

        foreach ($units as $index => [$plate, $type, $model, $status]) {
            Vehiculo::updateOrCreate(
                ['placa' => $plate],
                [
                    'descripcion' => $type === 'JAC' ? 'Unidad JAC' : "Autobús {$type} {$model}",
                    'external_id' => sprintf('DEMO-%02d', $index + 1),
                    'tipo' => $type,
                    'modelo' => $model,
                    'estado' => $status,
                    'source' => 'manual',
                ]
            );
        }
    }
}
