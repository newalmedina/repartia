<?php

namespace App\Support;

/**
 * Lista única de permisos de la aplicación.
 * Para añadir uno nuevo basta con ponerlo aquí: aparece solo en el formulario de roles.
 */
class Permissions
{
    // Pedidos
    public const PACKAGES_VIEW_OWN = 'packages.view_own';
    public const PACKAGES_VIEW_ALL = 'packages.view_all';
    public const PACKAGES_CREATE   = 'packages.create';
    public const PACKAGES_EDIT     = 'packages.edit';
    public const PACKAGES_DELETE   = 'packages.delete';
    public const PACKAGES_BYPASS_LOCK = 'packages.bypass_lock';

    // Categorías de pedido
    public const CATEGORIES_VIEW   = 'categories.view';
    public const CATEGORIES_CREATE = 'categories.create';
    public const CATEGORIES_EDIT   = 'categories.edit';
    public const CATEGORIES_DELETE = 'categories.delete';

    // Usuarios
    public const USERS_VIEW   = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_EDIT   = 'users.edit';
    public const USERS_DELETE = 'users.delete';
    public const USERS_EDIT_PRICE = 'users.edit_price';

    // Mi centro
    public const CENTER_EDIT = 'center.edit';

    // Pagos a repartidores
    public const PAYMENTS_VIEW   = 'payments.view';
    public const PAYMENTS_CREATE = 'payments.create';
    public const PAYMENTS_EDIT   = 'payments.edit';
    public const PAYMENTS_DELETE = 'payments.delete';

    // Informes
    public const REPORTS_VIEW = 'reports.view';

    /**
     * Permisos agrupados por módulo: [grupo => [label grupo, [permiso => label]]]
     */
    public static function groups(): array
    {
        return [
            'packages' => [
                'label' => 'Pedidos',
                'permissions' => [
                    self::PACKAGES_VIEW_OWN => 'Ver mis pedidos',
                    self::PACKAGES_VIEW_ALL => 'Ver todos los pedidos del centro',
                    self::PACKAGES_CREATE   => 'Crear pedidos',
                    self::PACKAGES_EDIT     => 'Editar pedidos',
                    self::PACKAGES_DELETE   => 'Eliminar pedidos',
                    self::PACKAGES_BYPASS_LOCK => 'Editar y eliminar pedidos cerrados (sin límite de 24 h)',
                ],
            ],
            'categories' => [
                'label' => 'Categorías de pedido',
                'permissions' => [
                    self::CATEGORIES_VIEW   => 'Ver categorías',
                    self::CATEGORIES_CREATE => 'Crear categorías',
                    self::CATEGORIES_EDIT   => 'Editar categorías',
                    self::CATEGORIES_DELETE => 'Eliminar categorías',
                ],
            ],
            'users' => [
                'label' => 'Usuarios',
                'permissions' => [
                    self::USERS_VIEW   => 'Ver usuarios',
                    self::USERS_CREATE => 'Crear usuarios',
                    self::USERS_EDIT   => 'Editar usuarios',
                    self::USERS_DELETE => 'Eliminar usuarios',
                    self::USERS_EDIT_PRICE => 'Modificar precio por paquete',
                ],
            ],
            'center' => [
                'label' => 'Mi centro',
                'permissions' => [
                    self::CENTER_EDIT => 'Editar mi centro',
                ],
            ],
            'payments' => [
                'label' => 'Pagos a repartidores',
                'permissions' => [
                    self::PAYMENTS_VIEW   => 'Ver pagos',
                    self::PAYMENTS_CREATE => 'Crear pagos',
                    self::PAYMENTS_EDIT   => 'Editar pagos',
                    self::PAYMENTS_DELETE => 'Eliminar pagos',
                ],
            ],
            'reports' => [
                'label' => 'Informes',
                'permissions' => [
                    self::REPORTS_VIEW => 'Ver y descargar informes',
                ],
            ],
        ];
    }

    /** Todos los permisos: [permiso => label] */
    public static function all(): array
    {
        return collect(self::groups())
            ->flatMap(fn($group) => $group['permissions'])
            ->all();
    }

    public static function exists(string $permission): bool
    {
        return array_key_exists($permission, self::all());
    }
}
