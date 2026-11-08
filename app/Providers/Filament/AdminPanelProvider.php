<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\AutenticatedUserAvatar;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Http\Middleware\AuthenticateAndCheckActive;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(config('app.name', 'Repartia'))
            ->brandLogoHeight('3rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->login()
            ->passwordReset(RequestPasswordReset::class)
            ->colors([
                'primary'   => Color::Blue,
                'secondary' => Color::Zinc,
                'success'   => Color::Emerald,
                'danger'    => Color::Red,
                'warning'   => Color::Yellow,
                'info'      => Color::Sky,
                'gray'      => Color::Slate,
            ])
            ->navigationGroups([
                'Reparto',
                'Informes',
                'Administración usuarios',
            ])
            ->defaultAvatarProvider(AutenticatedUserAvatar::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            // El dashboard (App\Filament\Pages\Dashboard) define qué widgets muestra
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \Filament\Widgets\AccountWidget::class,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Perfil')
                    ->url(fn() => route('filament.admin.pages.profile'))
                    ->icon('heroicon-o-user'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AuthenticateAndCheckActive::class,
            ]);
    }

    public function boot(): void
    {
        // Si el centro del usuario tiene logo, se usa como logo del panel;
        // si no, se muestra el nombre del centro
        Filament::serving(function () {
            $center = auth()->user()?->center;

            if (!$center) {
                return;
            }

            $panel = Filament::getCurrentPanel();

            if (!empty($center->image)) {
                $panel->brandLogo(Storage::url($center->image));
            } else {
                $panel->brandName($center->name);
            }
        });
    }
}
