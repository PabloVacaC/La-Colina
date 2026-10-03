<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpPedido extends Model
{
    use HasFactory;
    protected $table = 'tmp_pedidos';
    protected $fillable = ['session_id', 'producto', 'detalle', 'cantidad', 'precio_unitario', 'precio_total'];

   
}
