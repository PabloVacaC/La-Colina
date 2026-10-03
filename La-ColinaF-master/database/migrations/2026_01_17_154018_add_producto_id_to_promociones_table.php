<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('promociones', function (Blueprint $table) {

            $table->unsignedBigInteger('producto_id')
                ->after('nombre');

            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::table('promociones', function (Blueprint $table) {

            $table->dropForeign(['producto_id']);
            $table->dropColumn('producto_id');

        });
    }
};