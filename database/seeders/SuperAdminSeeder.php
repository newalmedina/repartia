<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Crea (o actualiza) el centro principal y los usuarios iniciales.
     *
     * Ejecutar solo este seeder:
     *   php artisan db:seed --class=SuperAdminSeeder
     */
    public function run(): void
    {
        // Las categorías y pedidos se filtran por el centro del usuario,
        // así que todos necesitan un centro para poder trabajar con ellos.
        $center = Center::query()->orderBy('id')->first()
            ?? Center::create([
                'name'   => 'Centro principal',
                'active' => true,
            ]);

        // Super administrador
        User::updateOrCreate(
            ['email' => 'ing.newal.medina@outlook.com'],
            [
                'name'              => 'Newal Medina',
                'password'          => Hash::make('Secret15*'),
                'center_id'         => $center->id,
                'super_admin'       => true,
                'active'            => true,
                'can_admin_panel'   => true,
                'price_per_package' => 0.96,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'ing.newal.medina@gmail.com'],
            [
                'name'              => 'Newal Medina',
                'password'          => Hash::make('Secret15*'),
                'center_id'         => $center->id,
                'super_admin'       => false,
                'active'            => true,
                'can_admin_panel'   => true,
                'price_per_package' => 0.86,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'rosa@gmail.com'],
            [
                'name'              => 'Rosa',
                'password'          => Hash::make('Secret15*'),
                'center_id'         => $center->id,
                'super_admin'       => false,
                'active'            => true,
                'can_admin_panel'   => true,
                'price_per_package' => 0.86,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Super administrador: ing.newal.medina@outlook.com');
        $this->command?->info('Usuarios: ing.newal.medina@gmail.com, rosa@gmail.com');
    }
}
