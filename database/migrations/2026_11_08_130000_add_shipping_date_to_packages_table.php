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
            // Fecha de envío del pedido (distinta de created_at, que es cuándo se registró)
            $table->date('shipping_date')->nullable()->after('order_number');
            $table->index(['center_id', 'shipping_date']);
        });

        // Pedidos que ya existían: su fecha de envío será el día en que se crearon
        DB::table('packages')
            ->whereNull('shipping_date')
            ->update(['shipping_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['center_id', 'shipping_date']);
            $table->dropColumn('shipping_date');
        });
    }
};
