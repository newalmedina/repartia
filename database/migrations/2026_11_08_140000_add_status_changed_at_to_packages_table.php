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
            // Cuándo se REGISTRÓ el último cambio a entregado/devuelto.
            // El bloqueo de 24 h cuenta desde aquí, no desde la fecha de entrega
            // (que puede ser anterior si se registra al día siguiente).
            $table->timestamp('status_changed_at')->nullable()->after('returned_at');
        });

        // Pedidos ya cerrados: se toma su fecha de entrega/devolución
        DB::table('packages')
            ->whereNull('status_changed_at')
            ->where(fn($q) => $q->where('delivered', true)->orWhere('returned', true))
            ->update(['status_changed_at' => DB::raw('COALESCE(delivered_at, returned_at)')]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
