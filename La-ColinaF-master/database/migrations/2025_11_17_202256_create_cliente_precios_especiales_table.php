<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientePreciosEspecialesTable extends Migration
{
    public function up()
    {
        Schema::create('cliente_precios_especiales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('precio_especial', 10, 2);
            $table->timestamps();

            $table->unique(['cliente_id','producto_id']);

            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cliente_precios_especiales');
    }
}