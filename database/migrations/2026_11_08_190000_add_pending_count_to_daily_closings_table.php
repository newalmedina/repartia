<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            // Cierre: pedidos que quedan pendientes (los pone el repartidor)
            $table->unsignedInteger('pending_count')->nullable()->after('returned_count');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn('pending_count');
        });
    }
};
