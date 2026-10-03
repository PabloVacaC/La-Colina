<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('ubicacion_gps')->nullable()->after('direccion');
            $table->decimal('latitud', 10, 7)->nullable()->after('ubicacion_gps');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['ubicacion_gps', 'latitud', 'longitud']);
        });
    }
};