<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('cierres_ventas_gastos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cierre_venta_id')
                ->constrained('cierres_ventas')
                ->cascadeOnDelete();

            $table->string('concepto');
            $table->decimal('monto', 10, 2);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cierres_ventas_gastos');
    }
};