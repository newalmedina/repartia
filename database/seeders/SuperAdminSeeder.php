<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Centro principal y usuarios iniciales, cada uno con su rol:
 *  - ing.newal.medina@outlook.com -> super administrador (todos los permisos)
 *  - ing.newal.medina@gmail.com   -> Administrador de centro
 *  - rosa@gmail.com               -> Repartidor
 *
 *   php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public const PASSWORD = 'Secret15*';

    public function run(): void
    {
        // Los roles tienen que existir para asignarlos
        $this->call(RoleSeeder::class);

        $center = Center::query()->orderBy('id')->first()
            ?? Center::create([
                'name'   => 'Centro principal',
                'active' => true,
            ]);

        $users = [
            [
                'email'             => 'ing.newal.medina@outlook.com',
                'name'              => 'SUPER ADMIN',
                'super_admin'       => true,
                'price_per_package' => 0.96,
                'roles'             => [], // el super admin no necesita roles
            ],
            [
                'email'             => 'ing.newal.medina@gmail.com',
                'name'              => 'Newal Medina',
                'super_admin'       => false,
                'price_per_package' => 0.86,
                'roles'             => ['Administrador de centro'],
            ],
            [
                'email'             => 'rosa@gmail.com',
                'name'              => 'Rosa',
                'super_admin'       => false,
                'price_per_package' => 0.86,
                'roles'             => ['Repartidor'],
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make(self::PASSWORD),
                    'center_id'         => $center->id,
                    'super_admin'       => $data['super_admin'],
                    'active'            => true,
                    'can_admin_panel'   => true,
                    'price_per_package' => $data['price_per_package'],
                    'email_verified_at' => now(),
                ]
            );

            $user->roles()->sync(Role::whereIn('name', $data['roles'])->pluck('id')->all());

            $this->command?->info(sprintf('%-32s %s', $data['email'], $data['super_admin'] ? 'super admin' : implode(', ', $data['roles'])));
        }
    }
}
