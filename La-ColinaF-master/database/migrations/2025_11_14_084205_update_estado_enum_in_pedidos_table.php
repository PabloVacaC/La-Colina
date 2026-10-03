<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pedidos 
            MODIFY estado 
            ENUM('Pendiente', 'Por asignar', 'Asignado', 'En camino', 'Entregado')
            NOT NULL 
            DEFAULT 'Pendiente'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pedidos 
            MODIFY estado 
            ENUM('Pendiente', 'Asignado', 'En camino', 'Entregado')
            NOT NULL 
            DEFAULT 'Pendiente'
        ");
    }
};