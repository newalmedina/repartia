<?php

namespace App\Support;

use App\Exports\ArrayExport;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Datos de los informes (los mismos para el panel y para el Excel).
 * Filtros: startDate, endDate, driverId, categoryId.
 * Respeta los permisos: centro del usuario y "ver todos / solo mis pedidos".
 */
class Reports
{
    public function __construct(protected array $filters = [])
    {
    }

    public static function make(array $filters = []): static
    {
        return new static($filters);
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    public function from(): Carbon
    {
        return !empty($this->filters['startDate'])
            ? Carbon::parse($this->filters['startDate'])->startOfDay()
            : now()->startOfMonth();
    }

    public function to(): Carbon
    {
        return !empty($this->filters['endDate'])
            ? Carbon::parse($this->filters['endDate'])->endOfDay()
            : now()->endOfDay();
    }

    public function driverId(): ?int
    {
        return Auth::user()?->canViewAllPackages() && !empty($this->filters['driverId'])
            ? (int) $this->filters['driverId']
            : null;
    }

    public function categoryId(): ?int
    {
        return !empty($this->filters['categoryId']) ? (int) $this->filters['categoryId'] : null;
    }

    public function periodLabel(): string
    {
        return $this->from()->format('d/m/Y') . ' - ' . $this->to()->format('d/m/Y');
    }

    /** Pedidos visibles para el usuario + filtros de repartidor y categoría */
    public function packages(): Builder
    {
        return Package::query()
            ->visibleTo(Auth::user())
            ->when($this->driverId(), fn(Builder $q, $id) => $q->where('packages.user_id', $id))
            ->when($this->categoryId(), fn(Builder $q, $id) => $q->where('packages.package_category_id', $id));
    }

    protected function range(): array
    {
        return [$this->from(), $this->to()];
    }

    protected function dateRange(): array
    {
        return [$this->from()->toDateString(), $this->to()->toDateString()];
    }

    protected static function euros($amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    // ------------------------------------------------------------------
    // Informes: cada uno devuelve [cabeceras, filas]
    // ------------------------------------------------------------------

    /** Por repartidor: entregados, cobrados, devueltos, comisiones, pagado y pendiente de pagar */
    public function drivers(): array
    {
        $range = $this->range();
        $user  = Auth::user();

        $drivers = User::query()
            ->where('center_id', $user?->center_id ?? -1)
            ->when(!$user?->canViewAllPackages(), fn($q) => $q->where('id', $user?->id ?? -1))
            ->when($this->driverId(), fn($q, $id) => $q->where('id', $id))
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($drivers as $driver) {
            $base = fn() => $this->packages()->where('packages.user_id', $driver->id);

            $delivered = $base()->delivered()->whereBetween('delivered_at', $range);
            $returned  = $base()->returned()->whereBetween('returned_at', $range)->count();

            $deliveredCount = (clone $delivered)->count();
            if ($deliveredCount === 0 && $returned === 0) {
                continue;
            }

            $commission = (float) (clone $delivered)->sum('delivery_price');
            $paid       = (float) (clone $delivered)->whereNotNull('payment_id')->sum('delivery_price');

            $rows[] = [
                $driver->name,
                User::paymentModeOptions()[$driver->payment_mode] ?? $driver->payment_mode,
                static::euros($driver->price_per_package),
                $deliveredCount,
                (clone $delivered)->where('delivery_price', '>', 0)->count(),
                (clone $delivered)->where('same_address', true)->count(),
                $returned,
                static::euros($commission),
                static::euros($paid),
                static::euros($commission - $paid),
            ];
        }

        return [
            ['Repartidor', 'Tipo de pago', '€/paquete', 'Entregados', 'Cobrados', 'Misma dirección (sin comisión)', 'Devueltos', 'Comisiones (€)', 'Pagado (€)', 'Pendiente de pagar (€)'],
            $rows,
        ];
    }

    /** Por categoría: enviados, entregados, devueltos, pendientes */
    public function categories(): array
    {
        $categories = PackageCategory::query()
            ->where('center_id', Auth::user()?->center_id ?? -1)
            ->when($this->categoryId(), fn($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($categories as $category) {
            $base = fn() => $this->packages()->where('packages.package_category_id', $category->id);

            $shipped   = $base()->whereBetween('shipping_date', $this->dateRange())->count();
            $delivered = $base()->delivered()->whereBetween('delivered_at', $this->range())->count();
            $returned  = $base()->returned()->whereBetween('returned_at', $this->range())->count();
            $pending   = $base()->pending()->whereBetween('shipping_date', $this->dateRange())->count();
            $closed    = $delivered + $returned;

            $rows[] = [
                $category->name,
                $shipped,
                $delivered,
                $returned,
                $pending,
                $closed > 0 ? round($delivered * 100 / $closed, 1) . '%' : '—',
                static::euros($base()->delivered()->whereBetween('delivered_at', $this->range())->sum('delivery_price')),
            ];
        }

        return [
            ['Categoría', 'Enviados', 'Entregados', 'Devueltos', 'Pendientes (enviados en el periodo)', '% entrega', 'Comisiones (€)'],
            $rows,
        ];
    }

    /** Evolución diaria */
    public function daily(): array
    {
        $count = function (Builder $query, string $column) {
            return $query->selectRaw("DATE({$column}) as day, COUNT(*) as total")->groupBy('day')->pluck('total', 'day');
        };

        $shipped    = $count($this->packages()->whereBetween('shipping_date', $this->dateRange()), 'shipping_date');
        $delivered  = $count($this->packages()->delivered()->whereBetween('delivered_at', $this->range()), 'delivered_at');
        $returned   = $count($this->packages()->returned()->whereBetween('returned_at', $this->range()), 'returned_at');
        $commission = $this->packages()->delivered()->whereBetween('delivered_at', $this->range())
            ->selectRaw('DATE(delivered_at) as day, SUM(delivery_price) as total')->groupBy('day')->pluck('total', 'day');

        $rows = [];
        for ($day = $this->from()->copy(); $day->lte($this->to()); $day->addDay()) {
            $key = $day->toDateString();
            $rows[] = [
                $day->format('d/m/Y'),
                (int) ($shipped[$key] ?? 0),
                (int) ($delivered[$key] ?? 0),
                (int) ($returned[$key] ?? 0),
                static::euros($commission[$key] ?? 0),
            ];
        }

        return [['Fecha', 'Enviados', 'Entregados', 'Devueltos', 'Comisiones (€)'], $rows];
    }

    /** Detalle de pedidos con fecha de envío en el periodo */
    public function packagesDetail(): array
    {
        $rows = $this->packages()
            ->whereBetween('shipping_date', $this->dateRange())
            ->with(['category', 'driver', 'payment'])
            ->orderBy('shipping_date')
            ->orderBy('order_number')
            ->get()
            ->map(fn(Package $p) => [
                $p->order_number,
                $p->shipping_date?->format('d/m/Y'),
                $p->status_label,
                $p->category?->name,
                $p->driver?->name,
                $p->recipient_name,
                $p->recipient_document,
                $p->address,
                $p->postal_code,
                $p->municipality,
                $p->province,
                $p->delivered_at?->format('d/m/Y H:i'),
                $p->returned_at?->format('d/m/Y H:i'),
                $p->delivery_price !== null ? static::euros($p->delivery_price) : '',
                $p->same_address ? 'Sí' : '',
                $p->payment?->number,
                $p->comments,
            ])
            ->all();

        return [
            ['Nº pedido', 'Fecha envío', 'Estado', 'Categoría', 'Repartidor', 'Destinatario', 'Documento', 'Dirección', 'CP', 'Municipio', 'Provincia', 'Entregado', 'Devuelto', 'Comisión (€)', 'Misma dirección', 'Pago', 'Comentarios'],
            $rows,
        ];
    }

    /**
     * Pagos a repartidores con fecha de pago en el periodo.
     * Devuelve además, por fila, los enlaces que el usuario puede usar (ver / editar / pdf).
     */
    public function payments(): array
    {
        $user = Auth::user();

        $payments = Payment::query()
            ->where('center_id', $user?->center_id ?? -1)
            ->when(!$user?->canViewAllPackages(), fn($q) => $q->where('user_id', $user?->id ?? -1))
            ->when($this->driverId(), fn($q, $id) => $q->where('user_id', $id))
            ->whereBetween('paid_at', $this->dateRange())
            ->with('driver')
            ->orderBy('paid_at')
            ->get();

        $resource = \App\Filament\Resources\PaymentResource::class;

        $links = $payments
            ->map(fn(Payment $p) => [
                'view' => $resource::canView($p) ? $resource::getUrl('view', ['record' => $p]) : null,
                'edit' => $resource::canEdit($p) ? $resource::getUrl('edit', ['record' => $p]) : null,
                'pdf'  => $resource::canView($p) ? route('payments.pdf', $p) : null,
            ])
            ->all();

        $rows = $payments
            ->map(fn(Payment $p) => [
                $p->number,
                $p->driver?->name,
                $p->period_from->format('d/m/Y') . ' - ' . $p->period_to->format('d/m/Y'),
                $p->paid_at?->format('d/m/Y'),
                $p->packages_count,
                static::euros($p->packages_amount),
                static::euros($p->extras_amount),
                static::euros($p->total),
                $p->notes,
            ])
            ->all();

        return [['Nº pago', 'Repartidor', 'Periodo', 'Pagado el', 'Pedidos', 'Comisiones (€)', 'Extras (€)', 'Total (€)', 'Notas'], $rows, $links];
    }

    // ------------------------------------------------------------------
    // Excel
    // ------------------------------------------------------------------

    /** Informes disponibles: clave => [título, método] */
    public static function catalog(): array
    {
        return [
            'drivers'    => ['Repartidores', 'drivers'],
            'categories' => ['Categorías', 'categories'],
            'daily'      => ['Evolución diaria', 'daily'],
            'packages'   => ['Detalle de pedidos', 'packagesDetail'],
            'payments'   => ['Pagos a repartidores', 'payments'],
        ];
    }

    public function excel(string $report)
    {
        [$title, $method] = static::catalog()[$report];
        [$headings, $rows] = $this->{$method}();

        $file = str($title)->slug() . '_' . $this->from()->format('Ymd') . '-' . $this->to()->format('Ymd') . '.xlsx';

        return Excel::download(new ArrayExport($headings, $rows, $title), $file);
    }

    /** Recibo de un pago en PDF (para enviárselo al repartidor) */
    public static function paymentReceiptPdf(Payment $payment)
    {
        $payment->loadMissing(['center', 'driver', 'packages', 'extras']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', ['payment' => $payment])
            ->setPaper('a4');

        $file = 'recibo_' . strtolower($payment->number) . '_' . str($payment->driver?->name)->slug() . '.pdf';

        return response()->streamDownload(fn() => print($pdf->output()), $file, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Recibo de un pago: pedidos, extras y total */
    public static function paymentReceiptExcel(Payment $payment)
    {
        $payment->loadMissing(['driver', 'packages', 'extras']);

        $rows = [
            ['Pago', $payment->number],
            ['Repartidor', $payment->driver?->name],
            ['Periodo', $payment->period_from->format('d/m/Y') . ' - ' . $payment->period_to->format('d/m/Y')],
            ['Pagado el', $payment->paid_at?->format('d/m/Y')],
            [],
            ['PEDIDOS', '', '', '', ''],
        ];

        foreach ($payment->packages->sortBy('delivered_at') as $p) {
            $rows[] = [
                $p->order_number,
                $p->delivered_at?->format('d/m/Y H:i'),
                trim($p->address . ', ' . $p->postal_code . ' ' . $p->municipality),
                $p->same_address ? 'Misma dirección' : '',
                static::euros($p->delivery_price),
            ];
        }

        $rows[] = [];
        $rows[] = ['EXTRAS', '', '', '', ''];
        foreach ($payment->extras as $extra) {
            $rows[] = [$extra->concept, '', '', '', static::euros($extra->amount)];
        }

        $rows[] = [];
        $rows[] = ['Comisiones (' . $payment->packages_count . ' pedidos)', '', '', '', static::euros($payment->packages_amount)];
        $rows[] = ['Extras', '', '', '', static::euros($payment->extras_amount)];
        $rows[] = ['TOTAL', '', '', '', static::euros($payment->total)];

        return Excel::download(
            new ArrayExport(['Recibo de pago', '', '', '', 'Importe (€)'], $rows, 'Recibo ' . $payment->number),
            'recibo_' . strtolower($payment->number) . '.xlsx',
        );
    }
}
