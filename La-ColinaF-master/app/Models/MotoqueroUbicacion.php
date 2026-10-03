<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotoqueroUbicacion extends Model
{
    protected $table = 'motoquero_ubicaciones';

    protected $fillable = [
        'motoquero_id',
        'latitud',
        'longitud',
        'estado',
        'registrado_en',
    ];

    protected $casts = [
        'registrado_en' => 'datetime',
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function pedido()
    {
        return $this->belongsTo(pedido::class);
    }
}