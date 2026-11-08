<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Centro y permisos
            $table->foreignId('center_id')->nullable()->after('id')
                ->constrained('centers')->nullOnDelete();
            $table->boolean('super_admin')->default(false)->after('center_id');
            $table->boolean('can_admin_panel')->default(false)->after('super_admin');
            $table->boolean('active')->default(true)->after('can_admin_panel');

            // Precio que se paga al repartidor por cada paquete entregado
            $table->decimal('price_per_package', 10, 2)->default(0)->after('active');

            // Datos personales
            $table->string('image')->nullable()->after('password');
            $table->string('phone')->nullable()->after('email');
            $table->date('birth_date')->nullable()->after('phone');
            $table->string('identification', 50)->nullable()->after('birth_date');
            $table->enum('gender', ['masc', 'fem'])->nullable()->after('identification');

            // Dirección
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('center_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn([
                'super_admin', 'can_admin_panel', 'active', 'price_per_package',
                'image', 'phone', 'birth_date', 'identification', 'gender',
                'address', 'postal_code',
            ]);
        });
    }
};
