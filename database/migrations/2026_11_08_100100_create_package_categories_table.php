<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('center_id', 'package_categories_center_id_fk')
                ->references('id')->on('centers')
                ->onDelete('cascade');

            // Un centro no puede repetir el nombre de categoría
            $table->unique(['center_id', 'name'], 'package_categories_center_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_categories');
    }
};
