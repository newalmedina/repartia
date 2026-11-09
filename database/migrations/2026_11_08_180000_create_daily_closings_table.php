<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cuadre de jornada: apertura (paquetes que salen) y cierre (repartidos / devueltos)
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // repartidor
            $table->date('date');

            // Apertura: lo pone el administrador
            $table->unsignedInteger('assigned_count')->nullable();

            // Cierre: lo pone el repartidor
            $table->unsignedInteger('delivered_count')->nullable();
            $table->unsignedInteger('returned_count')->nullable();

            // Confirmaciones (usuario autenticado que confirma)
            $table->foreignId('driver_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('driver_confirmed_at')->nullable();
            $table->foreignId('admin_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_confirmed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un solo cuadre por repartidor y día
            $table->unique(['user_id', 'date']);
            $table->index(['center_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
