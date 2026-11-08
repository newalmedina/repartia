<?php

namespace App\Models;

use App\Support\Permissions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Precio por paquete entregado por defecto para usuarios nuevos (en euros) */
    public const DEFAULT_PRICE_PER_PACKAGE = 0.90;

    /** Tipos de pago al repartidor */
    public const PAYMENT_PER_PACKAGE = 'per_package';
    public const PAYMENT_PER_ADDRESS = 'per_address';

    protected $guarded = [];

    /** Permisos ya calculados en esta petición */
    protected ?array $permissionCache = null;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'birth_date'        => 'date',
        'active'            => 'boolean',
        'super_admin'       => 'boolean',
        'can_admin_panel'   => 'boolean',
        'price_per_package' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Si el super admin eligió un centro en el formulario, se respeta;
            // si no, se hereda el centro del usuario que lo crea
            if (empty($model->center_id) && Auth::check()) {
                $model->center_id = Auth::user()->center_id;
            }
        });
    }

    /**
     * El acceso real al panel lo controla el middleware AuthenticateAndCheckActive
     * (usuario activo + can_admin_panel).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    // Relaciones
    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public static function paymentModeOptions(): array
    {
        return [
            self::PAYMENT_PER_PACKAGE => 'Por paquete',
            self::PAYMENT_PER_ADDRESS => 'Por dirección y día',
        ];
    }

    /** ¿Se le paga una sola vez por dirección entregada en el día? */
    public function paysPerAddress(): bool
    {
        return $this->payment_mode === self::PAYMENT_PER_ADDRESS;
    }

    /** Un usuario puede tener varios roles; sus permisos se suman */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /** Todos los permisos del usuario (suma de sus roles), cacheado por petición */
    public function permissionList(): array
    {
        return $this->permissionCache ??= $this->roles
            ->flatMap(fn(Role $role) => $role->permissionList())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * ¿Tiene este permiso? El super admin los tiene todos.
     * También funciona $user->can('packages.create') gracias al Gate (AuthServiceProvider).
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->super_admin) {
            return true;
        }

        return in_array($permission, $this->permissionList(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** ¿Puede ver los pedidos de todo el centro o solo los suyos? */
    public function canViewAllPackages(): bool
    {
        return $this->hasPermission(Permissions::PACKAGES_VIEW_ALL);
    }

    /** Pedidos asignados como repartidor */
    public function packages()
    {
        return $this->hasMany(Package::class, 'user_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Scopes
    public function scopeMyCenter($query)
    {
        return $query->where('users.center_id', Auth::user()?->center_id ?? -1);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->image) {
            return Storage::url($this->image);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }
}
