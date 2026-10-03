<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimientos_contables', function (Blueprint $table) {
            $table->id();

            // ingreso | gasto
            $table->enum('tipo', ['ingreso', 'gasto']);

            // venta, distribucion, produccion, administrativo, reinversion, otro_ingreso
            $table->string('categoria');

            // combustible, viatico, tapas, sueldos, etc (opcional)
            $table->string('subcategoria')->nullable();

            // texto libre
            $table->string('descripcion')->nullable();

            $table->decimal('monto', 12, 2);

            $table->date('fecha');

            // Para cierres mensuales y reportes rápidos
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');

            // Relación opcional (pedido, distribuidor, etc.)
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_tipo')->nullable();

            $table->timestamps();

            $table->index(['tipo', 'categoria']);
            $table->index(['mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_contables');
    }
};