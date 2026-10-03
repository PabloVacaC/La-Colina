<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {

            $table->boolean('promo_activa')
                ->default(false)
                ->after('longitud'); // ajusta según tu tabla

            $table->dateTime('promo_desde')
                ->nullable()
                ->after('promo_activa');

            $table->dateTime('promo_hasta')
                ->nullable()
                ->after('promo_desde');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'promo_activa',
                'promo_desde',
                'promo_hasta'
            ]);
        });
    }
};