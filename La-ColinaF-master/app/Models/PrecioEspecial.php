<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrecioEspecial extends Model
{
    use HasFactory;

    protected $table = 'cliente_precios_especiales';

    protected $fillable = [
        'cliente_id',
        'producto_id',
        'precio_especial',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}