<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            // Cliente
            $table->unsignedBigInteger('cliente_id');
            $table->foreign('cliente_id')
                  ->references('id')
                  ->on('clientes')
                  ->onDelete('cascade');

            // Tarifa (opcional)
            $table->unsignedBigInteger('tarifa_id')->nullable();
            $table->foreign('tarifa_id')
                  ->references('id')
                  ->on('tarifas')
                  ->onDelete('cascade');

            // Motoquero (opcional)
            $table->unsignedBigInteger('motoquero_id')->nullable();
            $table->foreign('motoquero_id')
                  ->references('id')
                  ->on('motoqueros')
                  ->onDelete('cascade');

            // Datos del pedido
            $table->decimal('total_precio', 10, 2);
            $table->enum('estado', ['Pendiente', 'En camino', 'Entregado', 'Cancelado']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
