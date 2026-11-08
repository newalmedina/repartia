<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        // Conecta nuestros permisos con el Gate de Laravel:
        // $user->can('packages.create'), @can('users.view'), middleware('can:...')
        Gate::before(function (User $user, string $ability) {
            if (Permissions::exists($ability)) {
                return $user->hasPermission($ability);
            }

            // Cualquier otra comprobación sigue su curso normal
            return null;
        });
    }
}
