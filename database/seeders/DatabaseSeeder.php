<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);
        // Países, provincias y municipios (para direcciones de usuarios y centros)

        // Super administrador + centro principal

        // Roles de ejemplo (editables desde "Roles y permisos")
        $this->call(RoleSeeder::class);
        $this->call(TestDataSeeder::class);
        $this->call(WorldTableSeeder::class);

        // Datos de prueba: NO se lanzan aquí. Para cargarlos:
        //   php artisan db:seed --class=TestDataSeeder
    }
}
