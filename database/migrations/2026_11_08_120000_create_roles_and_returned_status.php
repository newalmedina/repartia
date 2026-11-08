<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roles (los gestiona el super admin y se asignan a los usuarios)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            // {"packages": ["packages.view_own", ...], "users": [...], ...}
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // Un usuario puede tener varios roles (sus permisos se suman)
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        // Pedidos devueltos
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('returned')->default(false)->after('delivered_at');
            $table->timestamp('returned_at')->nullable()->after('returned');
            $table->index(['center_id', 'returned']);
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['center_id', 'returned']);
            $table->dropIndex(['delivered_at']);
            $table->dropColumn(['returned', 'returned_at']);
        });

        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
