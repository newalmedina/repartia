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
    public const PAYMENTS_VIEW_OWN = 'payments.view_own';
    public const PAYMENTS_VIEW   = 'payments.view';
    public const PAYMENTS_CREATE = 'payments.create';
    public const PAYMENTS_EDIT   = 'payments.edit';
    public const PAYMENTS_DELETE = 'payments.delete';

    // Informes
    public const REPORTS_VIEW = 'reports.view';

    // Aplicación móvil
    public const APP_ACCESS = 'app.access';

    // Cuadres de jornada
    public const CLOSINGS_VIEW_OWN      = 'closings.view_own';
    public const CLOSINGS_VIEW_ALL      = 'closings.view_all';
    public const CLOSINGS_CREATE        = 'closings.create';
    public const CLOSINGS_EDIT          = 'closings.edit';
    public const CLOSINGS_DELETE        = 'closings.delete';
    public const CLOSINGS_CONFIRM_ADMIN = 'closings.confirm_admin';

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
                    self::PAYMENTS_VIEW_OWN => 'Ver mis pagos (los que me han hecho)',
                    self::PAYMENTS_VIEW   => 'Ver todos los pagos del centro',
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
            'app' => [
                'label' => 'Aplicación móvil',
                'permissions' => [
                    self::APP_ACCESS => 'Acceso a la aplicación móvil',
                ],
            ],
            'closings' => [
                'label' => 'Cuadres de jornada',
                'permissions' => [
                    self::CLOSINGS_VIEW_OWN      => 'Ver mis cuadres',
                    self::CLOSINGS_VIEW_ALL      => 'Ver todos los cuadres del centro',
                    self::CLOSINGS_CREATE        => 'Crear cuadres (apertura)',
                    self::CLOSINGS_EDIT          => 'Editar cuadres',
                    self::CLOSINGS_DELETE        => 'Eliminar cuadres',
                    self::CLOSINGS_CONFIRM_ADMIN => 'Confirmar como administrador (y fijar paquetes entregados)',
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
