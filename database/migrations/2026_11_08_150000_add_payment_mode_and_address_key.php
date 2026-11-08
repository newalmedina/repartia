<?php

use App\Models\Package;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cómo se le paga al repartidor: por paquete o por dirección distinta en el día
        Schema::table('users', function (Blueprint $table) {
            $table->string('payment_mode', 20)->default('per_package')->after('price_per_package');
        });

        Schema::table('packages', function (Blueprint $table) {
            // Dirección normalizada (sin mayúsculas, tildes ni signos) para saber si es "la misma"
            $table->string('address_key', 32)->nullable()->after('country');
            // true = no cobra porque ya se pagó otra entrega en esa dirección ese día
            $table->boolean('same_address')->default(false)->after('delivery_price');
            $table->index(['user_id', 'address_key']);
        });

        // Clave de dirección para los pedidos que ya existen
        DB::table('packages')
            ->select(['id', 'address', 'postal_code', 'municipality'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('packages')->where('id', $row->id)->update([
                        'address_key' => Package::makeAddressKey($row->address, $row->postal_code, $row->municipality),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'address_key']);
            $table->dropColumn(['address_key', 'same_address']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });
    }
};
