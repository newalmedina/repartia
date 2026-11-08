<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorldTableSeeder extends Seeder
{
    /**
     * Países, provincias y municipios (más de 150.000 filas).
     */
    public function run(): void
    {
        // Son muchos datos: sin límite de memoria ni de tiempo solo durante este seeder
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        // No guardar en memoria cada consulta ejecutada
        DB::disableQueryLog();

        $this->call('Altwaireb\World\Database\Seeders\BaseWorldSeeder');
    }
}
