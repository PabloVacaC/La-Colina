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
           Schema::create('llamadas', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('cliente_id');
        $table->string('nombre_cliente');
        $table->string('celular_cliente');
        $table->unsignedBigInteger('motoquero_id')->nullable();
        $table->string('nombre_motoquero')->nullable();
        $table->enum('estado', ['pendiente', 'atendida'])->default('pendiente');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llamadas');
    }
};
