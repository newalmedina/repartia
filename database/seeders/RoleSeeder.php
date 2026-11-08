<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions as P;
use Illuminate\Database\Seeder;

/**
 * Roles de ejemplo. Se pueden editar después desde "Roles y permisos".
 *   php artisan db:seed --class=RoleSeeder
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
                'description' => 'Gestiona todos los pedidos y las categorías del centro',
                'permissions' => [
                    P::PACKAGES_VIEW_ALL, P::PACKAGES_CREATE, P::PACKAGES_EDIT, P::PACKAGES_DELETE,
                    P::CATEGORIES_VIEW, P::CATEGORIES_CREATE, P::CATEGORIES_EDIT,
                    P::USERS_VIEW,
                    P::PAYMENTS_VIEW, P::REPORTS_VIEW,
                ],
            ],
            'Atención al cliente' => [
                'description' => 'Ve todos los pedidos y puede darlos de alta',
                'permissions' => [P::PACKAGES_VIEW_ALL, P::PACKAGES_CREATE],
            ],
            'Repartidor' => [
                'description' => 'Ve solo sus pedidos y los marca como entregados o devueltos',
                'permissions' => [P::PACKAGES_VIEW_OWN, P::PACKAGES_EDIT],
            ],
            'Solo consulta' => [
                'description' => 'Ve todo el centro pero no puede modificar nada',
                'permissions' => [P::PACKAGES_VIEW_ALL, P::CATEGORIES_VIEW, P::USERS_VIEW, P::PAYMENTS_VIEW, P::REPORTS_VIEW],
            ],
        ];
    }

    public function run(): void
    {
        foreach (static::roles() as $name => $data) {
            // Se guardan agrupados por módulo, igual que en el formulario
            $grouped = collect($data['permissions'])
                ->groupBy(fn(string $permission) => explode('.', $permission)[0])
                ->map(fn($items) => $items->values()->all())
                ->all();

            Role::updateOrCreate(
                ['name' => $name],
                ['description' => $data['description'], 'permissions' => $grouped],
            );
        }

        $this->command?->info('Roles de ejemplo creados: ' . implode(', ', array_keys(static::roles())));
    }
}
