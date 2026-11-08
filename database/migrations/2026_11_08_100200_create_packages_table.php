<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();

            // Centro: se copia del usuario que crea el pedido
            $table->unsignedBigInteger('center_id')->nullable();
            // Categoría del pedido (de su centro)
            $table->unsignedBigInteger('package_category_id')->nullable();
            // Repartidor asignado
            $table->unsignedBigInteger('user_id')->nullable();
            // Usuario que creó el pedido
            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('order_number', 100);
            $table->json('photos')->nullable();

            // Destinatario
            $table->string('recipient_name', 255);
            $table->string('recipient_document', 50)->nullable();

            // Dirección de entrega
            $table->string('address', 255);
            $table->string('province', 150)->nullable();
            $table->string('municipality', 150)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 100)->default('ESPAÑA');

            // Entrega
            $table->boolean('delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            // Se rellena con el price_per_package del repartidor al marcarse como entregado
            $table->decimal('delivery_price', 10, 2)->nullable();

            $table->text('comments')->nullable();

            $table->timestamps();

            $table->foreign('center_id', 'packages_center_id_fk')
                ->references('id')->on('centers')->onDelete('set null');
            // restrict: no se puede borrar una categoría que ya esté usada
            $table->foreign('package_category_id', 'packages_category_id_fk')
                ->references('id')->on('package_categories')->onDelete('restrict');
            $table->foreign('user_id', 'packages_user_id_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'packages_created_by_fk')
                ->references('id')->on('users')->onDelete('set null');

            // El número de pedido no se repite dentro de un mismo centro
            $table->unique(['center_id', 'order_number'], 'packages_center_order_unique');
            $table->index(['center_id', 'delivered']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
