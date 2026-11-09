<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions as P;
use Illuminate\Database\Seeder;

/**
 * Roles de la plataforma. Se pueden editar después desde "Roles y permisos".
 * El super administrador tiene siempre todos los permisos (no necesita rol).
 *
 *   php artisan db:seed --class=RoleSeeder
 *
 * ┌──────────────────────────┬─────────────────────────────────────────────────────────────┐
 * │ Rol                      │ Para quién                                                  │
 * ├──────────────────────────┼─────────────────────────────────────────────────────────────┤
 * │ Administrador de centro  │ Responsable del centro: todo dentro de su centro            │
 * │ Jefe de tráfico          │ Organiza el reparto: pedidos, categorías y cuadres          │
 * │ Contabilidad             │ Paga a los repartidores e informes                          │
 * │ Atención al cliente      │ Da de alta pedidos y consulta su estado                     │
 * │ Repartidor               │ Sus pedidos, pagos, informes y cuadres + app móvil          │
 * │ Solo consulta            │ Ve todo el centro sin poder modificar nada                  │
 * └──────────────────────────┴─────────────────────────────────────────────────────────────┘
 */
class RoleSeeder extends Seeder
{
    public static function roles(): array
    {
        return [
            'Administrador de centro' => [
                'description' => 'Todo dentro de su centro',
                'permissions' => array_keys(P::all()),
            ],

            'Jefe de tráfico' => [
                'description' => 'Organiza el reparto: pedidos, categorías, repartidores y cuadres de jornada',
                'permissions' => [
                    // Pedidos: todos los del centro, sin saltarse el bloqueo de 24 h
                    P::PACKAGES_VIEW_ALL, P::PACKAGES_CREATE, P::PACKAGES_EDIT, P::PACKAGES_DELETE,
                    // Categorías (sin eliminar)
                    P::CATEGORIES_VIEW, P::CATEGORIES_CREATE, P::CATEGORIES_EDIT,
                    // Ve a los repartidores (no los crea ni cambia precios)
                    P::USERS_VIEW,
                    // Cuadres: abre, edita y confirma como administrador
                    P::CLOSINGS_VIEW_ALL, P::CLOSINGS_CREATE, P::CLOSINGS_EDIT, P::CLOSINGS_CONFIRM_ADMIN,
                    // Consulta pagos e informes
                    P::PAYMENTS_VIEW, P::REPORTS_VIEW,
                    // Aplicación móvil
                    P::APP_ACCESS,
                ],
            ],

            'Contabilidad' => [
                'description' => 'Pagos a repartidores, precios por paquete e informes',
                'permissions' => [
                    P::PACKAGES_VIEW_ALL,
                    P::CATEGORIES_VIEW,
                    P::USERS_VIEW, P::USERS_EDIT, P::USERS_EDIT_PRICE,
                    P::PAYMENTS_VIEW, P::PAYMENTS_CREATE, P::PAYMENTS_EDIT, P::PAYMENTS_DELETE,
                    P::REPORTS_VIEW,
                    P::CLOSINGS_VIEW_ALL,
                ],
            ],

            'Atención al cliente' => [
                'description' => 'Da de alta pedidos y consulta su estado',
                'permissions' => [
                    P::PACKAGES_VIEW_ALL, P::PACKAGES_CREATE, P::PACKAGES_EDIT,
                    P::CATEGORIES_VIEW,
                ],
            ],

            'Repartidor' => [
                'description' => 'Sus pedidos (crear a su nombre, ver y editar en 24 h), sus pagos, sus informes, sus cuadres y la app móvil',
                'permissions' => [
                    // Pedidos: solo los suyos (crea a su nombre; edita dentro de las 24 h)
                    P::PACKAGES_VIEW_OWN, P::PACKAGES_CREATE, P::PACKAGES_EDIT,
                    // Pagos que le han hecho a él (con PDF)
                    P::PAYMENTS_VIEW_OWN,
                    // Informes: solo con sus datos (no tiene "ver todos los pedidos")
                    P::REPORTS_VIEW,
                    // Cuadres: rellena el cierre de los suyos y confirma como repartidor
                    P::CLOSINGS_VIEW_OWN, P::CLOSINGS_EDIT,
                    // Aplicación móvil
                    P::APP_ACCESS,
                ],
            ],

            'Solo consulta' => [
                'description' => 'Ve todo el centro, sin poder modificar nada',
                'permissions' => [
                    P::PACKAGES_VIEW_ALL,
                    P::CATEGORIES_VIEW,
                    P::USERS_VIEW,
                    P::PAYMENTS_VIEW,
                    P::REPORTS_VIEW,
                    P::CLOSINGS_VIEW_ALL,
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (static::roles() as $name => $data) {
            // Se guardan agrupados por módulo, igual que en el formulario de roles
            $grouped = collect($data['permissions'])
                ->unique()
                ->groupBy(fn(string $permission) => explode('.', $permission)[0])
                ->map(fn($items) => $items->values()->all())
                ->all();

            Role::updateOrCreate(
                ['name' => $name],
                ['description' => $data['description'], 'permissions' => $grouped],
            );
        }

        $this->command?->info('Roles: ' . implode(', ', array_keys(static::roles())));
    }
}
