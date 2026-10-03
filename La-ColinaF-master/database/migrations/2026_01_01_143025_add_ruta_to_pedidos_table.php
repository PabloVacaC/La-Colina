<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {

            // Ruta A / B / C / D
            $table->char('ruta', 1)
                  ->nullable()
                  ->after('orden');

            // Índice para búsquedas frecuentes
            $table->index(['motoquero_id', 'ruta', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {

            $table->dropIndex(['motoquero_id', 'ruta', 'estado']);
            $table->dropColumn('ruta');
        });
    }
};