<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pagos de comisiones a repartidores
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // repartidor
            $table->date('period_from');
            $table->date('period_to');
            $table->date('paid_at')->nullable();
            // Importes guardados en el momento del pago
            $table->unsignedInteger('packages_count')->default(0);
            $table->decimal('packages_amount', 10, 2)->default(0);
            $table->decimal('extras_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['center_id', 'user_id', 'period_from']);
        });

        // Importes extra de un pago (con su comentario)
        Schema::create('payment_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('concept', 255);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        // Pedido -> pago en el que se pagó su comisión
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('same_address')
                ->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::dropIfExists('payment_extras');
        Schema::dropIfExists('payments');
    }
};
