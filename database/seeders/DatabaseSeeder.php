<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Países, provincias y municipios (para direcciones de usuarios y centros)
        $this->call(WorldTableSeeder::class);

        // Roles (editables desde "Roles y permisos")
        $this->call(RoleSeeder::class);

        // Centro principal y usuarios iniciales con sus roles
        $this->call(SuperAdminSeeder::class);

        // Datos de prueba: NO se lanzan aquí. Para cargarlos:
        //   php artisan db:seed --class=TestDataSeeder
    }
}
