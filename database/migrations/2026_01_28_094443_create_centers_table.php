<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('nif', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('image', 255)->nullable();

            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('postal_code', 255)->nullable();
            $table->string('address', 255)->nullable();

            $table->string('bank_name', 255)->nullable();
            $table->string('bank_number', 255)->nullable();

            // Colores usados en la plantilla de correo
            $table->string('primary_color', 50)->default('#2D3748');
            $table->string('primary_color_soft', 50)->default('#b8b8b8');

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('state_id')->references('id')->on('states')->onDelete('set null');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
