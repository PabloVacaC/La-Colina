<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('pedidos', function (Blueprint $table) {
        $table->integer('orden')->default(0)->after('estado'); // campo para ordenamiento
    });
}

public function down()
{
    Schema::table('pedidos', function (Blueprint $table) {
        $table->dropColumn('orden');
    });
}

};
