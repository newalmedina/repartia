<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pago de comisiones a un repartidor por los pedidos entregados en un periodo,
 * más importes extra opcionales. Un pedido pagado ya no se puede modificar.
 */
class Payment extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'period_from'     => 'date',
        'period_to'       => 'date',
        'paid_at'         => 'date',
        'packages_amount' => 'decimal:2',
        'extras_amount'   => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function (Payment $payment) {
            if (Auth::check()) {
                $payment->center_id ??= Auth::user()->center_id;
                $payment->created_by ??= Auth::id();
            }
        });

        // Al borrar un pago, sus pedidos vuelven a quedar "sin pagar"
        static::deleting(function (Payment $payment) {
            DB::table('packages')->where('payment_id', $payment->id)->update(['payment_id' => null]);
        });
    }

    // Relaciones
    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    /** Repartidor al que se paga */
    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function extras()
    {
        return $this->hasMany(PaymentExtra::class);
    }

    public function getNumberAttribute(): string
    {
        return 'PAGO-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function scopeMyCenter(Builder $query): Builder
    {
        return $query->where('payments.center_id', Auth::user()?->center_id ?? -1);
    }

    /**
     * Pedidos ENTREGADOS por un repartidor en un periodo (por fecha de entrega).
     * Incluye los ya pagados en otros pagos (para mostrarlos deshabilitados).
     */
    public static function deliveredPackagesFor(?int $userId, $from, $to, ?int $centerId = null): Collection
    {
        if (!$userId || !$from || !$to) {
            return new Collection();
        }

        return Package::query()
            ->where('center_id', $centerId ?? Auth::user()?->center_id ?? -1)
            ->where('user_id', $userId)
            ->where('delivered', true)
            ->whereBetween('delivered_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->with('payment')
            ->orderBy('delivered_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Deja asociados a este pago exactamente los pedidos indicados
     * (solo entregados del repartidor, sin pagar o ya de este pago) y recalcula importes.
     */
    public function syncPackages(array $packageIds): void
    {
        $packageIds = array_map('intval', $packageIds);

        DB::transaction(function () use ($packageIds) {
            // Los que se quitan vuelven a "sin pagar"
            DB::table('packages')
                ->where('payment_id', $this->id)
                ->whereNotIn('id', $packageIds ?: [0])
                ->update(['payment_id' => null]);

            if ($packageIds) {
                DB::table('packages')
                    ->whereIn('id', $packageIds)
                    ->where('user_id', $this->user_id)
                    ->where('center_id', $this->center_id)
                    ->where('delivered', true)
                    ->where(fn($q) => $q->whereNull('payment_id')->orWhere('payment_id', $this->id))
                    ->update(['payment_id' => $this->id]);
            }

            $this->recalculateTotals();
        });
    }

    public function recalculateTotals(): void
    {
        $packagesAmount = (float) $this->packages()->sum('delivery_price');
        $extrasAmount   = (float) $this->extras()->sum('amount');

        $this->forceFill([
            'packages_count'  => $this->packages()->count(),
            'packages_amount' => $packagesAmount,
            'extras_amount'   => $extrasAmount,
            'total'           => $packagesAmount + $extrasAmount,
        ])->saveQuietly();
    }
}
