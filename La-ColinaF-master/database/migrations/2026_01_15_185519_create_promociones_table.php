<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');
            $table->decimal('precio_promo', 10, 2);

            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');

            $table->boolean('activa')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};