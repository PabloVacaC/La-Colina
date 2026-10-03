<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Tabla
    |--------------------------------------------------------------------------
    | Esta tabla guardará todos los mensajes entrantes desde WhatsApp.
    */
    protected $table = 'whatsapp_messages';

    /*
    |--------------------------------------------------------------------------
    | Campos asignables
    |--------------------------------------------------------------------------
    | Estos son los campos que se podrán guardar mediante create() o fill().
    */
    protected $fillable = [
        'from',        // Número del contacto que envía el mensaje
        'name',        // Nombre del contacto (si WhatsApp lo provee)
        'message',     // Texto del mensaje recibido
        'received_at', // Fecha y hora exacta en la que llegó el mensaje
    ];

    /*
    |--------------------------------------------------------------------------
    | Fechas
    |--------------------------------------------------------------------------
    | Laravel tratará 'received_at' como un objeto Carbon automáticamente.
    */
    protected $dates = [
        'received_at',
    ];
}