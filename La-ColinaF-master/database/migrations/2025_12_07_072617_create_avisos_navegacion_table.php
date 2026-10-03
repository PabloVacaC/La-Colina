<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_navegacion', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pedido_id');
            $table->string('cliente');
            $table->string('celular');
            $table->unsignedBigInteger('motoquero_id');
            
            // estados: pendiente, atendido, cerrado
            $table->enum('estado', ['pendiente', 'atendido', 'cerrado'])->default('pendiente');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_navegacion');
    }
};