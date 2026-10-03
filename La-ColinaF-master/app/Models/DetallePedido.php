<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetallePedido extends Model
{
    use HasFactory;
    protected $table = 'detalle_pedidos';
    protected $fillable = ['pedido_id', 'producto', 'cantidad', 'precio_unitario', 'precio_total'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
    
}
