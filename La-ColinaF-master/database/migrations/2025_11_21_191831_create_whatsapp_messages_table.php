<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsAppMessagesTable extends Migration
{
    /**
     * Crear tabla whatsapp_messages
     */
    public function up()
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Campos principales
            |--------------------------------------------------------------------------
            | from → número de teléfono que envió el WhatsApp
            | name → nombre del contacto si WhatsApp lo envía
            | message → contenido del mensaje recibido
            | received_at → fecha/hora exacta de recepción
            */
            $table->string('from')->index();       // Ej: "59177777777"
            $table->string('name')->nullable();     // Opcional
            $table->text('message')->nullable();    // Texto del mensaje
            $table->timestamp('received_at');       // Cuándo llegó

            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Eliminar tabla
     */
    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
}