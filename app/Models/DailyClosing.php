<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Cuadre de jornada de un repartidor en un día.
 *  - Apertura: el administrador indica los paquetes que se le entregan (assigned_count).
 *  - Cierre: el repartidor indica repartidos (delivered_count) y devueltos (returned_count).
 *  - Para confirmar, todo debe cuadrar con lo registrado en el sistema
 *    (pedidos de ese repartidor con esa FECHA DE ENVÍO).
 */
class DailyClosing extends Model
{
    public const STATUS_OPEN      = 'open';       // falta completar datos
    public const STATUS_MISMATCH  = 'mismatch';   // datos completos pero no cuadra
    public const STATUS_PENDING   = 'pending';    // cuadra, falta confirmar
    public const STATUS_PARTIAL   = 'partial';    // confirmado por uno de los dos
    public const STATUS_CLOSED    = 'closed';     // confirmado por ambos

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'date'                => 'date',
        'driver_confirmed_at' => 'datetime',
        'admin_confirmed_at'  => 'datetime',
    ];

    /** Recuentos del sistema ya calculados (por petición) */
    protected ?array $systemCountsCache = null;

    /** Solo para datos de prueba: permite crear sin que el anterior esté cerrado */
    public static bool $skipPreviousCheck = false;

    protected static function booted()
    {
        static::creating(function (DailyClosing $closing) {
            if (Auth::check()) {
                $closing->center_id ??= Auth::user()->center_id;
                $closing->created_by ??= Auth::id();
            }

            // Para abrir un cuadre, el anterior de ese repartidor tiene que estar cerrado
            if (!static::$skipPreviousCheck && ($message = static::openingBlockedReason($closing->user_id, $closing->date))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['date' => $message]);
            }
        });

        // Si cambia algún número (o el repartidor / la fecha), las confirmaciones dejan de valer
        static::saving(function (DailyClosing $closing) {
            if ($closing->exists && $closing->isDirty(['user_id', 'date', 'assigned_count', 'delivered_count', 'returned_count', 'pending_count'])) {
                $closing->driver_confirmed_by = null;
                $closing->driver_confirmed_at = null;
                $closing->admin_confirmed_by  = null;
                $closing->admin_confirmed_at  = null;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    /** Repartidor */
    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driverConfirmer()
    {
        return $this->belongsTo(User::class, 'driver_confirmed_by');
    }

    public function adminConfirmer()
    {
        return $this->belongsTo(User::class, 'admin_confirmed_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ------------------------------------------------------------------
    // Visibilidad
    // ------------------------------------------------------------------

    /** Su centro; y sin "ver todos", solo los suyos */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= Auth::user();

        $query->where('daily_closings.center_id', $user?->center_id ?? -1);

        if (!$user?->hasPermission(Permissions::CLOSINGS_VIEW_ALL)) {
            $query->where('daily_closings.user_id', $user?->id ?? -1);
        }

        return $query;
    }

    // ------------------------------------------------------------------
    // Cuadre anterior
    // ------------------------------------------------------------------

    /** Último cuadre del repartidor con fecha ANTERIOR a la indicada */
    public static function previousFor(?int $userId, $date, ?int $exceptId = null): ?self
    {
        if (!$userId || !$date) {
            return null;
        }

        return static::query()
            ->where('user_id', $userId)
            ->whereDate('date', '<', \Carbon\Carbon::parse($date)->toDateString())
            ->when($exceptId, fn($q) => $q->whereKeyNot($exceptId))
            ->orderByDesc('date')
            ->first();
    }

    /** Motivo por el que no se puede abrir un cuadre (null = se puede) */
    public static function openingBlockedReason(?int $userId, $date): ?string
    {
        $previous = static::previousFor($userId, $date);

        if (!$previous || $previous->isClosed()) {
            return null;
        }

        $missing = collect([
            !$previous->isDriverConfirmed() ? 'el repartidor' : null,
            !$previous->isAdminConfirmed() ? 'el administrador' : null,
        ])->filter()->implode(' y ');

        return 'No se puede abrir: el cuadre anterior del ' . $previous->date->format('d/m/Y')
            . " está sin confirmar por {$missing}. Ciérralo primero.";
    }

    // ------------------------------------------------------------------
    // Recuentos del sistema y comprobaciones
    // ------------------------------------------------------------------

    /**
     * Pedidos del repartidor con esa fecha de envío, según el sistema:
     * ['assigned' => total, 'delivered' => entregados, 'returned' => devueltos,
     *  'pending' => pendientes, 'not_delivered' => no repartidos (devueltos + pendientes)]
     */
    public static function systemCountsFor(?int $userId, $date, ?int $centerId = null): array
    {
        if (!$userId || !$date) {
            return ['assigned' => 0, 'delivered' => 0, 'returned' => 0, 'pending' => 0, 'not_delivered' => 0];
        }

        $row = Package::query()
            ->where('center_id', $centerId ?? Auth::user()?->center_id ?? -1)
            ->where('user_id', $userId)
            ->whereDate('shipping_date', $date)
            ->selectRaw('COUNT(*) as assigned')
            ->selectRaw('SUM(CASE WHEN delivered = 1 THEN 1 ELSE 0 END) as delivered')
            ->selectRaw('SUM(CASE WHEN returned = 1 THEN 1 ELSE 0 END) as returned')
            ->toBase()
            ->first();

        $assigned  = (int) ($row->assigned ?? 0);
        $delivered = (int) ($row->delivered ?? 0);
        $returned  = (int) ($row->returned ?? 0);

        return [
            'assigned'      => $assigned,
            'delivered'     => $delivered,
            'returned'      => $returned,
            'pending'       => $assigned - $delivered - $returned,
            'not_delivered' => $assigned - $delivered, // lo que vuelve: devueltos + pendientes
        ];
    }

    public function systemCounts(): array
    {
        return $this->systemCountsCache ??= static::systemCountsFor($this->user_id, $this->date, $this->center_id);
    }

    /**
     * Qué no cuadra. Vacío = todo cuadra y se puede confirmar.
     * Sirve tanto para un cuadre guardado como para los datos de un formulario.
     */
    public static function problemsFor(?int $assigned, ?int $delivered, ?int $returned, ?int $pending, array $system): array
    {
        $problems = [];

        if ($assigned === null) {
            $problems[] = 'Falta el número de paquetes entregados al repartidor (lo pone el administrador).';
        } elseif ($assigned !== $system['assigned']) {
            $problems[] = "Paquetes entregados: el administrador indica {$assigned} y en el sistema hay {$system['assigned']} pedidos con esa fecha de envío.";
        }

        if ($delivered === null || $returned === null || $pending === null) {
            $problems[] = 'Faltan repartidos, devueltos y/o pendientes (los pone el repartidor).';
        } else {
            if ($delivered !== $system['delivered']) {
                $problems[] = "Repartidos: el repartidor indica {$delivered} y en el sistema hay {$system['delivered']} entregados.";
            }
            // Lo no entregado cuadra en conjunto: devueltos + pendientes = no entregados del sistema
            if ($returned + $pending !== $system['not_delivered']) {
                $problems[] = 'Devueltos + pendientes: el repartidor indica ' . ($returned + $pending)
                    . " y en el sistema hay {$system['not_delivered']} sin entregar.";
            }
            if ($assigned !== null && $delivered + $returned + $pending !== $assigned) {
                $problems[] = 'Repartidos + devueltos + pendientes (' . ($delivered + $returned + $pending) . ") no es igual a los paquetes entregados ({$assigned}).";
            }
        }

        return $problems;
    }

    public function problems(): array
    {
        return static::problemsFor($this->assigned_count, $this->delivered_count, $this->returned_count, $this->pending_count, $this->systemCounts());
    }

    public function isBalanced(): bool
    {
        return $this->problems() === [];
    }

    // ------------------------------------------------------------------
    // Estado y confirmaciones
    // ------------------------------------------------------------------

    public function isDriverConfirmed(): bool
    {
        return $this->driver_confirmed_at !== null;
    }

    public function isAdminConfirmed(): bool
    {
        return $this->admin_confirmed_at !== null;
    }

    /** Confirmado por los dos: cerrado */
    public function isClosed(): bool
    {
        return $this->isDriverConfirmed() && $this->isAdminConfirmed();
    }

    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->isClosed()                                                    => self::STATUS_CLOSED,
            $this->isDriverConfirmed() || $this->isAdminConfirmed()              => self::STATUS_PARTIAL,
            $this->assigned_count === null || $this->delivered_count === null
                || $this->returned_count === null || $this->pending_count === null => self::STATUS_OPEN,
            !$this->isBalanced()                                                 => self::STATUS_MISMATCH,
            default                                                              => self::STATUS_PENDING,
        };
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN     => 'Abierto',
            self::STATUS_MISMATCH => 'No cuadra',
            self::STATUS_PENDING  => 'Cuadra · sin confirmar',
            self::STATUS_PARTIAL  => 'Confirmado por uno',
            self::STATUS_CLOSED   => 'Cerrado',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_OPEN     => 'gray',
            self::STATUS_MISMATCH => 'danger',
            self::STATUS_PENDING  => 'warning',
            self::STATUS_PARTIAL  => 'info',
            self::STATUS_CLOSED   => 'success',
        ];
    }

    /** ¿Puede este usuario confirmar como repartidor? (solo el propio repartidor o el super admin) */
    public function canConfirmAsDriver(?User $user): bool
    {
        return $user !== null
            && !$this->isDriverConfirmed()
            && ($user->id === $this->user_id || $user->super_admin);
    }

    /** ¿Puede confirmar como administrador? (permiso "Confirmar como administrador") */
    public function canConfirmAsAdmin(?User $user): bool
    {
        return $user !== null
            && !$this->isAdminConfirmed()
            && $user->hasPermission(Permissions::CLOSINGS_CONFIRM_ADMIN);
    }

    public function confirmAsDriver(User $user): void
    {
        $this->forceFill(['driver_confirmed_by' => $user->id, 'driver_confirmed_at' => now()])->saveQuietly();
    }

    public function confirmAsAdmin(User $user): void
    {
        $this->forceFill(['admin_confirmed_by' => $user->id, 'admin_confirmed_at' => now()])->saveQuietly();
    }
}
