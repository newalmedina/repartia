<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DATOS DE PRUEBA solo para el centro principal:
 * categorías y pedidos desde el día 1 del mes pasado hasta hoy,
 * repartidos entre los dos usuarios de SuperAdminSeeder.
 * No crea centros ni usuarios.
 *
 *   php artisan db:seed --class=TestDataSeeder
 *
 * Se puede ejecutar varias veces: borra y regenera solo los pedidos de prueba (PRB-...).
 */
class TestDataSeeder extends Seeder
{
    protected array $categories = [
        'Paquetería estándar' => true,
        'Urgente 24h'         => true,
        'Frágil'              => true,
        'Documentación'       => true,
        'Voluminoso'          => true,
        'Devolución a origen' => false, // inactiva, para probar
    ];

    protected array $towns = [
        ['Barcelona', '080'], ['Badalona', '089'], ['L\'Hospitalet de Llobregat', '089'],
        ['Sabadell', '082'], ['Terrassa', '082'], ['Cornellà de Llobregat', '089'],
    ];

    protected array $streets = [
        'Carrer de Mallorca', 'Avinguda Diagonal', 'Carrer de Sants', 'Gran Via de les Corts Catalanes',
        'Carrer de Balmes', 'Rambla del Poblenou', 'Carrer d\'Aragó', 'Passeig de Sant Joan',
    ];

    protected array $firstNames = ['María', 'José', 'Antonio', 'Carmen', 'Francisco', 'Isabel', 'Manuel', 'Laura', 'David', 'Ana', 'Javier', 'Cristina', 'Daniel', 'Marta', 'Alejandro', 'Lucía', 'Pablo', 'Paula', 'Sergio', 'Elena', 'Jorge', 'Raquel', 'Adrián', 'Sofía', 'Rubén', 'Alba'];

    protected array $lastNames = ['García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín', 'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno', 'Muñoz', 'Álvarez', 'Romero', 'Navarro', 'Torres'];

    protected array $companies = ['Talleres Hermanos Gil S.L.', 'Farmacia Central', 'Óptica Visión Plus', 'Librería El Faro', 'Clínica Dental Sonrisa', 'Ferretería La Llave'];

    protected array $comments = [
        'Dejar en portería si no hay nadie.',
        'Llamar antes de subir, el timbre no funciona.',
        'Horario de entrega preferente: mañanas.',
        'Cliente ausente en el primer intento.',
        'Paquete con la caja ligeramente dañada.',
        'Piso 3º sin ascensor.',
    ];

    public function run(): void
    {
        mt_srand(20261108); // mismos datos en cada ejecución

        $center = Center::query()->orderBy('id')->first();

        $drivers = User::query()
            ->whereIn('email', ['ing.newal.medina@gmail.com', 'ing.newal.medina@outlook.com', 'rosa@gmail.com'])
            ->where('center_id', $center?->id)
            ->get()
            ->values()
            ->all();

        if (!$center || count($drivers) === 0) {
            $this->command?->error('Primero ejecuta: php artisan db:seed --class=SuperAdminSeeder');

            return;
        }

        // Para ver los dos tipos de pago en los ejemplos:
        //   Newal (gmail) -> por dirección y día      Rosa y outlook -> por paquete
        User::where('email', 'ing.newal.medina@gmail.com')->update(['payment_mode' => User::PAYMENT_PER_ADDRESS]);
        User::whereIn('email', ['ing.newal.medina@outlook.com', 'rosa@gmail.com'])->update(['payment_mode' => User::PAYMENT_PER_PACKAGE]);
        $drivers = array_map(fn(User $u) => $u->fresh(), $drivers);

        $from = now()->subMonthNoOverflow()->startOfMonth();
        $to   = now();

        $total = DB::transaction(function () use ($center, $drivers, $from, $to) {
            // Pagos de prueba anteriores (sus pedidos se regeneran)
            Payment::where('center_id', $center->id)->where('notes', 'like', 'Pago de prueba%')->get()->each->delete();

            $categories = $this->makeCategories($center);
            $count      = $this->makePackages($center, $categories, $drivers, $from, $to);

            $this->makeExamplePayments($center, $drivers);

            return $count;
        });

        $this->command?->info("{$total} pedidos de prueba en \"{$center->name}\" del {$from->format('d-m-Y')} al {$to->format('d-m-Y')}");
    }

    /** @return array<int, PackageCategory> solo las activas */
    protected function makeCategories(Center $center): array
    {
        $active = [];

        foreach ($this->categories as $name => $isActive) {
            $category = PackageCategory::updateOrCreate(
                ['center_id' => $center->id, 'name' => $name],
                ['active' => $isActive],
            );

            if ($isActive) {
                $active[] = $category;
            }
        }

        return $active;
    }

    protected function makePackages(Center $center, array $categories, array $drivers, Carbon $from, Carbon $to): int
    {
        DB::table('packages')
            ->where('center_id', $center->id)
            ->where('order_number', 'like', 'PRB-%')
            ->delete();

        $creatorId = collect($drivers)->firstWhere('email', 'ing.newal.medina@gmail.com')?->id ?? $drivers[0]->id;
        $rows  = [];
        $total = 0;

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            // Menos pedidos en fin de semana
            $count = $day->isWeekend() ? mt_rand(0, 4) : mt_rand(10, 18);
            $dayRows = [];

            for ($i = 1; $i <= $count; $i++) {
                $createdAt = $day->copy()->setTime(mt_rand(7, 19), mt_rand(0, 59), mt_rand(0, 59));

                if ($createdAt->gt($to)) {
                    continue;
                }

                $row = $this->packageRow($center, $categories, $drivers, $creatorId, $createdAt, $day, $i);

                // ~15%: misma dirección y mismo repartidor que el pedido anterior del día
                // (otro destinatario), para probar el "pago por dirección y día"
                $previous = $dayRows[count($dayRows) - 1] ?? null;
                if ($previous && $previous['delivered'] && $row['delivered'] && mt_rand(1, 100) <= 15) {
                    $row['user_id']        = $previous['user_id'];
                    $row['delivery_price'] = $previous['delivery_price'];
                    $row['address']        = $previous['address'];
                    $row['postal_code']    = $previous['postal_code'];
                    $row['municipality']   = $previous['municipality'];
                    $row['delivered_at']   = Carbon::parse($previous['delivered_at'])->addMinutes(mt_rand(1, 5));
                    $row['status_changed_at'] = $row['delivered_at'];
                    $row['updated_at']     = $row['delivered_at'];
                }

                $row['address_key'] = Package::makeAddressKey($row['address'], $row['postal_code'], $row['municipality']);
                $row['same_address'] = false;

                $rows[]    = $row;
                $dayRows[] = $row;
                $total++;

                if (count($rows) >= 500) {
                    DB::table('packages')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows) {
            DB::table('packages')->insert($rows);
        }

        // Ejemplos fijos de "misma dirección, repartidor y día"
        $total += $this->makeAddressExamples($center, $categories, $drivers, $creatorId);

        // Aplica el tipo de pago de cada repartidor (por paquete o por dirección y día)
        Package::recalculateAllAddresses($center->id);

        return $total;
    }

    protected function packageRow(Center $center, array $categories, array $drivers, int $creatorId, Carbon $createdAt, Carbon $day, int $seq): array
    {
        $ageHours = $createdAt->diffInHours(now());

        // Lo reciente sigue pendiente; lo antiguo casi todo cerrado
        $roll = mt_rand(1, 100);
        if ($ageHours < 6) {
            $status = $roll <= 85 ? 'pending' : 'delivered';
        } elseif ($ageHours < 48) {
            $status = $roll <= 45 ? 'pending' : ($roll <= 93 ? 'delivered' : 'returned');
        } else {
            $status = $roll <= 4 ? 'pending' : ($roll <= 90 ? 'delivered' : 'returned');
        }

        // Algunos pendientes sin repartidor
        $driver = ($status === 'pending' && mt_rand(1, 100) <= 25) ? null : $drivers[array_rand($drivers)];

        // Cierre entre 2 y 30 horas después, nunca en el futuro
        $closedAt = null;
        if ($status !== 'pending') {
            $closedAt = $createdAt->copy()->addHours(mt_rand(2, 30))->addMinutes(mt_rand(0, 59));
            if ($closedAt->gt(now())) {
                $closedAt = now()->subMinutes(mt_rand(5, 90));
            }
            if ($closedAt->lt($createdAt)) {
                $closedAt = $createdAt->copy()->addMinutes(30);
            }
        }

        [$town, $cpPrefix] = $this->towns[array_rand($this->towns)];
        $isCompany = mt_rand(1, 100) <= 15;

        return [
            'center_id'           => $center->id,
            'package_category_id' => $categories[array_rand($categories)]->id,
            'user_id'             => $driver?->id,
            'created_by'          => $creatorId,
            'order_number'        => sprintf('PRB-%s-%03d', $day->format('ymd'), $seq),
            'shipping_date'       => $day->toDateString(),
            'photos'              => null,
            'recipient_name'      => $isCompany ? $this->companies[array_rand($this->companies)] : $this->personName(),
            'recipient_document'  => $isCompany ? 'B' . mt_rand(10000000, 99999999) : $this->dni(),
            'address'             => $this->streets[array_rand($this->streets)] . ', ' . mt_rand(1, 250),
            'province'            => 'Barcelona',
            'municipality'        => $town,
            'postal_code'         => $cpPrefix . str_pad((string) mt_rand(1, 99), 2, '0', STR_PAD_LEFT),
            'country'             => 'ESPAÑA',
            'delivered'           => $status === 'delivered',
            'delivered_at'        => $status === 'delivered' ? $closedAt : null,
            'returned'            => $status === 'returned',
            'returned_at'         => $status === 'returned' ? $closedAt : null,
            'status_changed_at'   => $closedAt,
            // Comisión = precio por paquete del repartidor (solo entregados)
            'delivery_price'      => $status === 'delivered' ? $driver->price_per_package : null,
            'comments'            => mt_rand(1, 100) <= 20 ? $this->comments[array_rand($this->comments)] : null,
            'created_at'          => $createdAt,
            'updated_at'          => $closedAt ?? $createdAt,
        ];
    }

    /**
     * Ejemplos fijos de "misma dirección, repartidor y día" (pedidos PRB-EJ-...),
     * de lunes de esta semana hasta hoy, para que salgan con el filtro por defecto.
     * Al terminar se muestra en consola qué debe cobrar cada caso.
     */
    protected function makeAddressExamples(Center $center, array $categories, array $drivers, int $creatorId): int
    {
        $newal = collect($drivers)->firstWhere('email', 'ing.newal.medina@gmail.com');   // por dirección y día
        $other = collect($drivers)->firstWhere('email', 'rosa@gmail.com')                // por paquete
            ?? collect($drivers)->firstWhere('email', 'ing.newal.medina@outlook.com');

        if (!$newal) {
            return 0;
        }

        $rows    = [];
        $summary = [];

        for ($day = now()->startOfWeek(Carbon::MONDAY)->startOfDay(); $day->lte(now()); $day->addDay()) {
            $seq = 0;

            $add = function (User $driver, string $recipient, string $address, string $cp, int $minute, string $status = 'delivered')
                use (&$rows, &$seq, $center, $categories, $creatorId, $day): ?string {
                $closedAt = $day->copy()->setTime(9, 0)->addMinutes($minute);

                // Nada en el futuro (hoy solo lo que ya ha pasado)
                if ($closedAt->gt(now())) {
                    return null;
                }

                $seq++;
                $number = sprintf('PRB-EJ-%s-%02d', $day->format('ymd'), $seq);

                $rows[] = [
                    'center_id'           => $center->id,
                    'package_category_id' => $categories[0]->id,
                    'user_id'             => $driver->id,
                    'created_by'          => $creatorId,
                    'order_number'        => $number,
                    'shipping_date'       => $day->toDateString(),
                    'photos'              => null,
                    'recipient_name'      => $recipient,
                    'recipient_document'  => $this->dni(),
                    'address'             => $address,
                    'province'            => 'Barcelona',
                    'municipality'        => 'Barcelona',
                    'postal_code'         => $cp,
                    'country'             => 'ESPAÑA',
                    'address_key'         => Package::makeAddressKey($address, $cp, 'Barcelona'),
                    'delivered'           => $status === 'delivered',
                    'delivered_at'        => $status === 'delivered' ? $closedAt : null,
                    'returned'            => $status === 'returned',
                    'returned_at'         => $status === 'returned' ? $closedAt : null,
                    'status_changed_at'   => $status === 'pending' ? null : $closedAt,
                    'delivery_price'      => $status === 'delivered' ? $driver->price_per_package : null,
                    'same_address'        => false,
                    'comments'            => 'Ejemplo de misma dirección, repartidor y día',
                    'created_at'          => $day->copy()->setTime(8, 0),
                    'updated_at'          => $closedAt,
                ];

                return $number;
            };

            $date = $day->format('d/m');

            // A) Newal: 3 paquetes a la misma dirección (una escrita distinto) -> cobra 1
            $a = array_filter([
                $add($newal, 'Fabiola Ruiz', 'Carrer de Balmes, 2, 2º 1ª', '08007', 0),
                $add($newal, 'Newal Medina', 'carrer de balmes 2 2º 1ª', '08007', 2),
                $add($newal, 'Marta Gómez',  'Carrer de Balmes, 2, 2º 1ª', '08007', 4),
            ]);
            if ($a) {
                $summary[] = [$date, 'A', 'Newal · Balmes 2, 2º 1ª · 3 destinatarios', implode(', ', $a), count($a) > 1 ? '1 cobra, ' . (count($a) - 1) . ' no' : 'cobra'];
            }

            // B) Newal: mismo edificio, otra puerta -> otra dirección, cobra aparte
            if ($b = $add($newal, 'Jordi Puig', 'Carrer de Balmes, 2, 2º 3ª', '08007', 6)) {
                $summary[] = [$date, 'B', 'Newal · Balmes 2, 2º 3ª (otra puerta)', $b, 'cobra'];
            }

            // E) Newal: misma dirección, uno devuelto y otro pendiente -> solo cuenta el entregado
            $e = array_filter([
                $add($newal, 'Sofía Torres', "Carrer d'Aragó, 100, 1º", '08015', 30),
                $add($newal, 'Iván Ruiz',    "Carrer d'Aragó, 100, 1º", '08015', 32, 'returned'),
                $add($newal, 'Alba Gil',     "Carrer d'Aragó, 100, 1º", '08015', 34, 'pending'),
            ]);
            if ($e) {
                $summary[] = [$date, 'E', "Newal · Aragó 100, 1º · entregado + devuelto + pendiente", implode(', ', $e), 'cobra solo el entregado'];
            }

            if ($other) {
                // C) Misma dirección, distinto repartidor -> cobran los dos
                $c = array_filter([
                    $add($newal, 'Laura Sánchez', 'Avinguda Diagonal, 400, 5º', '08037', 20),
                    $add($other, 'Pablo Torres',  'Avinguda Diagonal, 400, 5º', '08037', 25),
                ]);
                if ($c) {
                    $summary[] = [$date, 'C', "Diagonal 400, 5º · Newal + {$other->name}", implode(', ', $c), 'cobran los dos'];
                }

                // D) Repartidor por paquete, misma dirección 2 veces -> cobran los dos
                $d = array_filter([
                    $add($other, 'Elena Díaz',   'Carrer de Sants, 15, bajo', '08014', 40),
                    $add($other, 'Rubén Moreno', 'Carrer de Sants, 15, bajo', '08014', 42),
                ]);
                if ($d) {
                    $summary[] = [$date, 'D', "{$other->name} (por paquete) · Sants 15, bajo", implode(', ', $d), 'cobran los dos'];
                }
            }
        }

        if ($rows) {
            DB::table('packages')->insert($rows);
        }

        $this->command?->newLine();
        $this->command?->info('Ejemplos de misma dirección (Newal cobra por dirección y día' . ($other ? ", {$other->name} por paquete" : '') . '):');
        $this->command?->table(['Día', 'Caso', 'Qué es', 'Pedidos', 'Debe cobrar'], $summary);
        $this->command?->line('  · Caso A repetido cada día: Newal cobra 1 por día en esa dirección (días distintos se pagan aparte).');

        return count($rows);
    }

    /**
     * Pagos de ejemplo de la SEMANA PASADA (lunes a domingo), uno por repartidor.
     * Sus pedidos quedan pagados y bloqueados. Esta semana queda sin pagar para probar.
     */
    protected function makeExamplePayments(Center $center, array $drivers): void
    {
        $from = now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $to   = now()->subWeek()->endOfWeek(Carbon::SUNDAY);

        foreach ($drivers as $driver) {
            $ids = Payment::deliveredPackagesFor($driver->id, $from, $to, $center->id)->pluck('id')->all();

            if (!$ids) {
                continue;
            }

            $payment = Payment::create([
                'center_id'   => $center->id,
                'user_id'     => $driver->id,
                'period_from' => $from->toDateString(),
                'period_to'   => $to->toDateString(),
                'paid_at'     => $to->copy()->addDay()->toDateString(),
                'notes'       => 'Pago de prueba de la semana pasada',
                'created_by'  => $driver->id,
            ]);

            if ($driver->email === 'ing.newal.medina@gmail.com') {
                $payment->extras()->create(['concept' => 'Gasolina', 'amount' => 10]);
            }

            $payment->syncPackages($ids);

            $this->command?->line("  · Pago de prueba {$payment->number} para {$driver->name}: {$payment->packages_count} pedidos, total "
                . number_format((float) $payment->total, 2, ',', '.') . ' €');
        }
    }

    protected function personName(): string
    {
        return $this->firstNames[array_rand($this->firstNames)] . ' '
            . $this->lastNames[array_rand($this->lastNames)] . ' '
            . $this->lastNames[array_rand($this->lastNames)];
    }

    /** DNI con letra válida */
    protected function dni(): string
    {
        $number = mt_rand(10000000, 99999999);

        return $number . 'TRWAGMYFPDXBNJZSQVHLCKE'[$number % 23];
    }
}
