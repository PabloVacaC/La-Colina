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
    Schema::table('llamadas', function (Blueprint $table) {
        $table->enum('estado', ['pendiente', 'atendida', 'cerrada'])
              ->default('pendiente')
              ->change();
    });
}

public function down(): void
{
    Schema::table('llamadas', function (Blueprint $table) {
        $table->enum('estado', ['pendiente', 'atendida'])
              ->default('pendiente')
              ->change();
    });
}
};
