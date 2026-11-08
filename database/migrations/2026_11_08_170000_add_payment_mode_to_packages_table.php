<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Forma de pago con la que se calculó la comisión de este pedido
            // (para saber si está calculado con una configuración antigua del repartidor)
            $table->string('payment_mode', 20)->nullable()->after('same_address');
        });

        // Pedidos entregados que ya existen
        DB::table('packages')->where('delivered', true)->where('same_address', true)
            ->update(['payment_mode' => 'per_address']);

        // Si en un grupo (repartidor + día + dirección) hay alguno a 0 € por "misma dirección",
        // todo el grupo se calculó "por dirección" (también el que cobra)
        DB::table('packages')
            ->where('same_address', true)
            ->selectRaw('user_id, DATE(delivered_at) as day, address_key')
            ->groupBy('user_id', 'day', 'address_key')
            ->get()
            ->each(fn($g) => DB::table('packages')
                ->where('user_id', $g->user_id)
                ->where('delivered', true)
                ->whereDate('delivered_at', $g->day)
                ->where('address_key', $g->address_key)
                ->update(['payment_mode' => 'per_address']));

        // El resto: la forma de pago que tiene ahora su repartidor
        DB::table('packages')->where('delivered', true)->whereNull('payment_mode')
            ->update(['payment_mode' => DB::raw('(SELECT users.payment_mode FROM users WHERE users.id = packages.user_id)')]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
