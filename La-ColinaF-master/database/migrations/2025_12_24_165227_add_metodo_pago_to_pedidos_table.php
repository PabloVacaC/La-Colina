<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('pedidos', function (Blueprint $table) {
        // Crear columna método de pago correctamente
        if (!Schema::hasColumn('pedidos', 'metodo_pago')) {
            $table->enum('metodo_pago', ['Efectivo', 'QR'])
                  ->nullable()
                  ->after('estado');
        }
    });
}

public function down(): void
{
    Schema::table('pedidos', function (Blueprint $table) {
        if (Schema::hasColumn('pedidos', 'metodo_pago')) {
            $table->dropColumn('metodo_pago');
        }
    });
}
};