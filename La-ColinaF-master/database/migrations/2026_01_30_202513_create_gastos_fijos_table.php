<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gastos_fijos', function (Blueprint $table) {
            $table->id();

            // Clasificación
            $table->string('categoria', 50);
            $table->string('subcategoria', 100)->nullable();

            // Concepto del gasto
            $table->string('concepto', 150);

            // Monto mensual
            $table->decimal('monto', 10, 2);

            // Día del mes de referencia (1–31)
            $table->unsignedTinyInteger('dia_referencia');

            // Permite desactivar sin borrar
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos_fijos');
    }
};