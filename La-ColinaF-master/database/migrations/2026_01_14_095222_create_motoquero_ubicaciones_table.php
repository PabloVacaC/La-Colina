<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('motoquero_ubicaciones', function (Blueprint $table) {
            $table->id();

            // Relación
            $table->foreignId('motoquero_id')
                ->constrained('motoqueros')
                ->onDelete('cascade');

            // Coordenadas
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);

            // Estado opcional (por si luego querés mostrar colores)
            $table->enum('estado', ['activo', 'pausado', 'offline'])
                  ->default('activo');

            // Momento exacto del GPS
            $table->timestamp('registrado_en');

            $table->timestamps();

            // Índices
            $table->index(['motoquero_id', 'registrado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motoquero_ubicaciones');
    }
};