<?php

namespace App\Models;

use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Package extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_RETURNED  = 'returned';

    /**
     * Qué hacer con importes calculados con una forma de pago / precio antiguos:
     * keep = mantener lo ya calculado | mode = aplicar forma de pago actual |
     * amount = aplicar precio actual | both = aplicar ambos
     */
    public const POLICY_KEEP   = 'keep';
    public const POLICY_MODE   = 'mode';
    public const POLICY_AMOUNT = 'amount';
    public const POLICY_BOTH   = 'both';

    /** Política para el próximo guardado (no es columna; la fija el formulario o la acción) */
    public ?string $pricingPolicy = null;

    /** Horas tras la entrega/devolución en las que todavía se puede modificar */
    public const LOCK_AFTER_HOURS = 24;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'shipping_date'  => 'date',
        'photos'         => 'array',
        'delivered'      => 'boolean',
        'delivered_at'   => 'datetime',
        'returned'       => 'boolean',
        'returned_at'    => 'datetime',
        'status_changed_at' => 'datetime',
        'same_address'   => 'boolean',
        'delivery_price' => 'decimal:2',
    ];

    protected $attributes = [
        'country'   => 'ESPAÑA',
        'delivered' => false,
        'returned'  => false,
    ];

    // "status" no es columna: se calcula a partir de delivered/returned
    protected $appends = ['status'];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING   => 'Pendiente',
            self::STATUS_DELIVERED => 'Entregado',
            self::STATUS_RETURNED  => 'Devuelto',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_PENDING   => 'warning',
            self::STATUS_DELIVERED => 'success',
            self::STATUS_RETURNED  => 'danger',
        ];
    }

    public static function statusIcons(): array
    {
        return [
            self::STATUS_PENDING   => 'heroicon-o-clock',
            self::STATUS_DELIVERED => 'heroicon-o-check-circle',
            self::STATUS_RETURNED  => 'heroicon-o-arrow-uturn-left',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            // El centro del pedido es SIEMPRE el del usuario que lo crea
            if (Auth::check()) {
                $model->center_id  = Auth::user()->center_id;
                $model->created_by = $model->created_by ?? Auth::id();
            }

            // Fecha de envío: hoy si no se indica
            $model->shipping_date ??= today();
        });

        static::saving(function ($model) {
            // Clave de dirección (para saber si dos entregas son "la misma dirección")
            if ($model->address_key === null || $model->isDirty(['address', 'postal_code', 'municipality'])) {
                $model->address_key = static::makeAddressKey($model->address, $model->postal_code, $model->municipality);
            }

            $model->applyStatusRules();
        });

        // Tras guardar o borrar, se recalcula quién cobra en las direcciones afectadas,
        // SOLO si cambia algo que afecta al pago (guardar sin cambios no recalcula nada)
        static::saved(function ($model) {
            $relevant = $model->wasRecentlyCreated
                || $model->wasChanged(['delivered', 'returned', 'user_id', 'delivered_at', 'address_key']);

            $policy = $model->pricingPolicy ?? self::POLICY_KEEP;

            if ($relevant || $policy !== self::POLICY_KEEP) {
                $model->recalculateAffectedAddresses(policy: $policy);
            }

            $model->pricingPolicy = null;
        });
        static::deleted(fn($model) => $model->recalculateAffectedAddresses(onlyCurrent: true));

        // Pasadas 24 h desde la entrega/devolución no se puede borrar por ningún camino
        // (tabla, edición, acciones masivas, API...). Devolver false cancela el borrado.
        static::deleting(function ($model) {
            return !$model->isLockedFor(Auth::user());
        });
    }

    // ------------------------------------------------------------------
    // Estado
    // ------------------------------------------------------------------

    public function getStatusAttribute(): string
    {
        if ($this->returned) {
            return self::STATUS_RETURNED;
        }

        return $this->delivered ? self::STATUS_DELIVERED : self::STATUS_PENDING;
    }

    /**
     * Columnas reales para un estado. Usar SIEMPRE esto al guardar:
     * "status" no es una columna y Laravel lo descarta en update()/create().
     *   $package->update(Package::statusAttributes('delivered'));
     */
    public static function statusAttributes(?string $status): array
    {
        $status ??= self::STATUS_PENDING;

        return [
            'delivered' => $status === self::STATUS_DELIVERED,
            'returned'  => $status === self::STATUS_RETURNED,
        ];
    }

    /** Columna de fecha de cierre de un estado (null para pendiente) */
    public static function closingDateColumn(string $status): ?string
    {
        return match ($status) {
            self::STATUS_DELIVERED => 'delivered_at',
            self::STATUS_RETURNED  => 'returned_at',
            default                => null,
        };
    }

    public static function closingDateLabel(string $status): string
    {
        return $status === self::STATUS_RETURNED ? 'Fecha de devolución' : 'Fecha de entrega';
    }

    /** Permite hacer $package->status = 'delivered' (asignación directa) */
    public function setStatusAttribute(?string $value): void
    {
        $value ??= self::STATUS_PENDING;

        $this->attributes['delivered'] = $value === self::STATUS_DELIVERED;
        $this->attributes['returned']  = $value === self::STATUS_RETURNED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status];
    }

    /**
     * - Entregado: guarda la fecha y copia el precio por paquete del repartidor (comisión).
     * - Devuelto: guarda la fecha; no genera comisión.
     * - Pendiente: limpia fechas y precio.
     */
    public function applyStatusRules(): void
    {
        // Se registra AHORA el cambio de estado (para el bloqueo de 24 h),
        // aunque la fecha de entrega elegida sea anterior
        if ($this->isDirty('delivered') || $this->isDirty('returned')) {
            $this->status_changed_at = ($this->delivered || $this->returned) ? now() : null;
        }

        if ($this->delivered) {
            $this->returned    = false;
            $this->returned_at = null;

            // Respeta la fecha elegida en el formulario; si no hay, ahora
            if (empty($this->delivered_at)) {
                $this->delivered_at = now();
            }

            // El repartidor del pedido AHORA (no la relación ya cargada, por si ha cambiado)
            $driver = $this->user_id ? User::find($this->user_id) : null;

            if ($this->isDirty('delivered') || $this->isDirty('user_id') || $this->delivery_price === null) {
                $this->delivery_price = $driver?->price_per_package;
                $this->same_address   = false;
                $this->payment_mode   = null; // se calculará en su dirección con la forma de pago actual
            }

            // Cambia de dirección o de día: es otra parada, se calcula como nuevo en ella
            $originalDate = $this->getOriginal('delivered_at') ? Carbon::parse($this->getOriginal('delivered_at'))->toDateString() : null;
            $dateChanged  = $originalDate !== null && $originalDate !== $this->delivered_at?->toDateString();

            if ($this->exists && ($this->isDirty('address_key') || $dateChanged)) {
                $this->same_address = false;
                $this->payment_mode = null;
                if ((float) $this->delivery_price <= 0) {
                    $this->delivery_price = $driver?->price_per_package;
                }
            }

            return;
        }

        $this->delivered_at   = null;
        $this->delivery_price = null;
        $this->same_address   = false;
        $this->payment_mode   = null;

        if ($this->returned) {
            if (empty($this->returned_at)) {
                $this->returned_at = now();
            }

            return;
        }

        $this->returned_at = null;
    }

    // ------------------------------------------------------------------
    // Pago por dirección y día
    // ------------------------------------------------------------------

    /**
     * Dirección normalizada: sin mayúsculas, tildes, º/ª ni signos, y con espacios unificados.
     *   "Carrer de Balmes, 2, 2º"  y  "carrer de balmes 2 2"   -> misma dirección
     *   "Balmes 2 piso 2"          y  "Balmes 2 piso 2.3"      -> direcciones distintas
     */
    public static function makeAddressKey(?string $address, ?string $postalCode, ?string $municipality): string
    {
        $text = implode(' ', [$address, $postalCode, $municipality]);
        $text = str_replace(['º', 'ª', '°'], '', $text);
        $text = strtolower(\Illuminate\Support\Str::ascii($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return md5(trim($text));
    }

    /** Recalcula la dirección actual y, si ha cambiado, también la anterior */
    public function recalculateAffectedAddresses(bool $onlyCurrent = false, string $policy = self::POLICY_KEEP): void
    {
        $groups = [];

        if ($this->delivered && $this->user_id && $this->delivered_at) {
            $groups[] = [$this->user_id, $this->delivered_at->toDateString(), $this->address_key];
        }

        if (!$onlyCurrent && $this->getOriginal('delivered') && $this->getOriginal('user_id') && $this->getOriginal('delivered_at')) {
            $groups[] = [
                $this->getOriginal('user_id'),
                Carbon::parse($this->getOriginal('delivered_at'))->toDateString(),
                $this->getOriginal('address_key') ?? $this->address_key,
            ];
        }

        foreach (array_unique($groups, SORT_REGULAR) as [$userId, $date, $addressKey]) {
            static::recalculateAddress($userId, $date, $addressKey, $policy);
        }
    }

    /** Entregas de un repartidor, un día, en una dirección (el "grupo") */
    protected static function addressGroup(int $userId, string $date, ?string $addressKey)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('delivered', true)
            ->whereDate('delivered_at', $date)
            ->where('address_key', $addressKey)
            ->orderBy('delivered_at')
            ->orderBy('id')
            ->get(['id', 'delivery_price', 'same_address', 'payment_id', 'payment_mode']);
    }

    /**
     * Recalcula quién cobra en un grupo (repartidor + día + dirección).
     *
     * keep   -> lo ya calculado NO se toca. Los pedidos nuevos del grupo se calculan con la
     *           forma de pago actual (por dirección: 0 € si la dirección ya cobra ese día).
     * mode   -> aplica la forma de pago actual del repartidor a todo el grupo (mantiene importes).
     * amount -> aplica el precio actual a los que cobran (mantiene la forma de pago con que se calculó).
     * both   -> recalcula todo con la forma de pago y el precio actuales.
     *
     * Grupos con algún pedido pagado no se tocan nunca.
     */
    public static function recalculateAddress(int $userId, string $date, ?string $addressKey, string $policy = self::POLICY_KEEP): void
    {
        $driver = User::find($userId);

        if (!$driver || !$addressKey) {
            return;
        }

        $packages = static::addressGroup($userId, $date, $addressKey);

        if ($packages->isEmpty() || $packages->contains(fn($p) => $p->payment_id !== null)) {
            return;
        }

        $driverPrice = (float) $driver->price_per_package;
        $groupPrice  = (float) $packages->max(fn($p) => (float) $p->delivery_price) ?: $driverPrice;
        $updates     = [];

        if ($policy === self::POLICY_KEEP) {
            // Calculados antes: se quedan como están. Nuevos (sin payment_mode): forma de pago actual.
            $hasPayer = $packages->contains(fn($p) => $p->payment_mode !== null && !$p->same_address && (float) $p->delivery_price > 0);

            foreach ($packages as $p) {
                if ($p->payment_mode !== null) {
                    continue;
                }

                if ($driver->paysPerAddress() && $hasPayer) {
                    $updates[$p->id] = [0, true, $driver->payment_mode];
                } else {
                    $updates[$p->id] = [(float) $p->delivery_price > 0 ? (float) $p->delivery_price : $driverPrice, false, $driver->payment_mode];
                    $hasPayer = true;
                }
            }

            // Si la dirección se quedó sin nadie que cobre (se quitó el que cobraba), cobra el siguiente
            $stillHasPayer = $packages->contains(fn($p) => isset($updates[$p->id]) ? !$updates[$p->id][1] : (!$p->same_address && (float) $p->delivery_price > 0));
            if (!$stillHasPayer) {
                $first = $packages->first();
                $updates[$first->id] = [$groupPrice, false, $first->payment_mode ?? $driver->payment_mode];
            }
        } else {
            $applyMode   = in_array($policy, [self::POLICY_MODE, self::POLICY_BOTH], true);
            $applyAmount = in_array($policy, [self::POLICY_AMOUNT, self::POLICY_BOTH], true);

            $mode = $applyMode
                ? $driver->payment_mode
                : ($packages->pluck('payment_mode')->filter()->first() ?? $driver->payment_mode);

            foreach ($packages->values() as $index => $p) {
                $same = $mode === User::PAYMENT_PER_ADDRESS && $index > 0;

                $price = match (true) {
                    $same        => 0,
                    $applyAmount => $driverPrice,
                    default      => (float) $p->delivery_price > 0 ? (float) $p->delivery_price : $groupPrice,
                };

                $updates[$p->id] = [$price, $same, $mode];
            }
        }

        foreach ($packages as $p) {
            if (!isset($updates[$p->id])) {
                continue;
            }

            [$price, $same, $mode] = $updates[$p->id];

            // update por consulta: no dispara eventos (evita bucles) ni toca updated_at
            if ((float) $p->delivery_price !== (float) $price || (bool) $p->same_address !== $same || $p->payment_mode !== $mode) {
                DB::table('packages')->where('id', $p->id)->update([
                    'delivery_price' => $price,
                    'same_address'   => $same,
                    'payment_mode'   => $mode,
                ]);
            }
        }
    }

    /**
     * ¿Los importes de este grupo se calcularon con otra forma de pago u otro precio
     * distintos de los actuales del repartidor? Devuelve las diferencias (vacío = al día).
     *   ['mode' => [antiguas formas, actual], 'amount' => [antiguos precios, actual], 'count' => n, 'total' => €]
     */
    public static function pricingStaleness(?int $userId, ?string $date, ?string $addressKey): array
    {
        $driver = $userId ? User::find($userId) : null;

        if (!$driver || !$date || !$addressKey) {
            return [];
        }

        $packages = static::addressGroup($userId, $date, $addressKey)
            ->filter(fn($p) => $p->payment_id === null && $p->payment_mode !== null);

        if ($packages->isEmpty()) {
            return [];
        }

        $diff = [];

        $oldModes = $packages->pluck('payment_mode')->unique()->reject(fn($m) => $m === $driver->payment_mode)->values()->all();
        if ($oldModes) {
            $diff['mode'] = [$oldModes, $driver->payment_mode];
        }

        $oldPrices = $packages
            ->filter(fn($p) => (float) $p->delivery_price > 0)
            ->map(fn($p) => round((float) $p->delivery_price, 2))
            ->unique()
            ->reject(fn($price) => $price === round((float) $driver->price_per_package, 2))
            ->values()
            ->all();
        if ($oldPrices) {
            $diff['amount'] = [$oldPrices, (float) $driver->price_per_package];
        }

        if ($diff) {
            $diff['count'] = $packages->count();
            $diff['total'] = (float) $packages->sum('delivery_price');
        }

        return $diff;
    }

    /** Otros pedidos entregados por el mismo repartidor, el mismo día, en la misma dirección */
    public function sameAddressSiblings(): Builder
    {
        return static::query()
            ->where('id', '!=', $this->id)
            ->where('user_id', $this->user_id ?? -1)
            ->where('delivered', true)
            ->whereDate('delivered_at', $this->delivered_at?->toDateString() ?? '1900-01-01')
            ->where('address_key', $this->address_key)
            ->orderBy('delivered_at')
            ->orderBy('id');
    }

    /** ¿Comparte dirección con otras entregas de ese repartidor ese día? */
    public function hasSameAddressSiblings(): bool
    {
        return $this->delivered && $this->user_id && $this->sameAddressSiblings()->exists();
    }

    /** El pedido de la misma dirección que sí cobra (para los marcados "misma dirección") */
    public function paidSameAddressSibling(): ?self
    {
        return $this->same_address
            ? $this->sameAddressSiblings()->where('same_address', false)->first()
            : null;
    }

    /**
     * Filtro SQL: pedidos que comparten dirección con otra entrega
     * del mismo repartidor el mismo día (el que cobra y los que no).
     */
    public function scopeInSharedAddress(Builder $query): Builder
    {
        return $query
            ->where('packages.delivered', true)
            ->whereExists(fn($sub) => $sub
                ->selectRaw('1')
                ->from('packages as siblings')
                ->whereColumn('siblings.user_id', 'packages.user_id')
                ->whereColumn('siblings.address_key', 'packages.address_key')
                ->whereColumn('siblings.id', '!=', 'packages.id')
                ->where('siblings.delivered', true)
                ->whereRaw('DATE(siblings.delivered_at) = DATE(packages.delivered_at)'));
    }

    /**
     * Pedidos entregados y SIN PAGAR de un repartidor (los que se pueden reajustar).
     */
    public static function unpaidDeliveredOf(int $userId): Builder
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('delivered', true)
            ->whereNull('payment_id');
    }

    /**
     * Aplica a los pedidos entregados y sin pagar del repartidor:
     *  - $amount: el nuevo precio por paquete (a los que cobran).
     *  - $mode:   la nueva forma de pago (recalcula qué entregas cobran por dirección).
     * Los pedidos pagados nunca se tocan.
     */
    public static function applyDriverPricing(User $driver, bool $amount, bool $mode): int
    {
        $policy = match (true) {
            $amount && $mode => self::POLICY_BOTH,
            $amount          => self::POLICY_AMOUNT,
            $mode            => self::POLICY_MODE,
            default          => self::POLICY_KEEP,
        };

        $query = static::unpaidDeliveredOf($driver->id);
        $count = (clone $query)->count();

        (clone $query)
            ->selectRaw('DATE(delivered_at) as day, address_key')
            ->groupBy('day', 'address_key')
            ->get()
            ->each(fn($group) => static::recalculateAddress($driver->id, $group->day, $group->address_key, $policy));

        return $count;
    }

    /** Recalcula todas las direcciones (por ejemplo, tras cargar datos de prueba) */
    public static function recalculateAllAddresses(?int $centerId = null, string $policy = self::POLICY_BOTH): void
    {
        static::query()
            ->where('delivered', true)
            ->whereNotNull('user_id')
            ->when($centerId, fn($q) => $q->where('center_id', $centerId))
            ->selectRaw('user_id, DATE(delivered_at) as day, address_key')
            ->groupBy('user_id', 'day', 'address_key')
            ->get()
            ->each(fn($group) => static::recalculateAddress($group->user_id, $group->day, $group->address_key, $policy));
    }

    /** Fecha en la que se cerró (entregado o devuelto) */
    public function getClosedAtAttribute(): ?Carbon
    {
        return $this->delivered_at ?? $this->returned_at;
    }

    /**
     * Momento desde el que cuentan las 24 h: cuando se REGISTRÓ como entregado/devuelto.
     * Si lo marcas hoy con fecha de entrega de ayer, tienes 24 h desde hoy para corregirlo.
     */
    public function lockStartsAt(): ?Carbon
    {
        if (!$this->delivered && !$this->returned) {
            return null;
        }

        return $this->status_changed_at ?? $this->closed_at;
    }

    /**
     * Registrado como entregado o devuelto hace más de 24 h: ya no se puede modificar ni eliminar.
     */
    public function isLocked(): bool
    {
        $start = $this->lockStartsAt();

        return $start !== null && $start->lte(now()->subHours(self::LOCK_AFTER_HOURS));
    }

    /**
     * ¿Está bloqueado PARA ESTE USUARIO?
     * Con el permiso "Editar y eliminar pedidos cerrados" nunca lo está (el super admin tampoco).
     */
    public function isLockedFor(?User $user): bool
    {
        // Pagado: no se modifica nunca (para cambiarlo hay que quitarlo del pago)
        if ($this->isPaid()) {
            return true;
        }

        if ($user?->hasPermission(Permissions::PACKAGES_BYPASS_LOCK)) {
            return false;
        }

        return $this->isLocked();
    }

    /** ¿Su comisión ya está incluida en un pago al repartidor? */
    public function isPaid(): bool
    {
        return $this->payment_id !== null;
    }

    /** Hasta cuándo se puede modificar (null si sigue pendiente) */
    public function editableUntil(): ?Carbon
    {
        return $this->lockStartsAt()?->copy()->addHours(self::LOCK_AFTER_HOURS);
    }

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function category()
    {
        return $this->belongsTo(PackageCategory::class, 'package_category_id');
    }

    /** Pago al repartidor en el que se incluyó (null = sin pagar) */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /** Repartidor asignado (user_id) */
    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Pedidos que puede ver un usuario:
     * - Siempre solo los de su centro.
     * - Con "ver todos los pedidos del centro": todos los del centro.
     * - Si no: solo los asignados a él.
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= Auth::user();

        $query->where('packages.center_id', $user?->center_id ?? -1);

        if (!$user?->canViewAllPackages()) {
            $query->where('packages.user_id', $user?->id ?? -1);
        }

        return $query;
    }

    public function scopeMyCenter($query)
    {
        return $query->where('packages.center_id', Auth::user()?->center_id ?? -1);
    }

    public function scopePending($query)
    {
        return $query->where('delivered', false)->where('returned', false);
    }

    public function scopeDelivered($query)
    {
        return $query->where('delivered', true);
    }

    public function scopeReturned($query)
    {
        return $query->where('returned', true);
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->postal_code,
            $this->municipality,
            $this->province,
            $this->country,
        ]));
    }
}
