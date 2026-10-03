<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreVentaGasto extends Model
{
    protected $table = 'cierres_ventas_gastos';

    protected $fillable = [
        'cierre_venta_id',
        'concepto',
        'monto',
    ];

    public function cierre()
    {
        return $this->belongsTo(CierreVenta::class, 'cierre_venta_id');
    }
}