<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('cierres_ventas', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');

            $table->foreignId('motoquero_id')
                ->constrained('motoqueros')
                ->cascadeOnDelete();

            $table->decimal('ingreso_bruto', 10, 2)->default(0);
            $table->decimal('ingreso_efectivo', 10, 2)->default(0);
            $table->decimal('ingreso_qr', 10, 2)->default(0);

            $table->decimal('total_gastos_distribucion', 10, 2)->default(0);

            $table->decimal('efectivo_entregado', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['fecha', 'motoquero_id']); // 1 cierre por día
        });
    }

    public function down()
    {
        Schema::dropIfExists('cierres_ventas');
    }
};