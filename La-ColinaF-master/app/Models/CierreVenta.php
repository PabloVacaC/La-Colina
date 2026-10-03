<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreVenta extends Model
{
    protected $table = 'cierres_ventas';

    protected $fillable = [
        'fecha',
        'motoquero_id',
        'ingreso_bruto',
        'ingreso_efectivo',
        'ingreso_qr',
        'total_gastos_distribucion',
        'efectivo_entregado',
    ];


    protected $casts = [
        'fecha' => 'date',    
    ];


    public function motoquero()
    {
        return $this->belongsTo(Motoquero::class);
    }

    public function gastos()
    {
        return $this->hasMany(CierreVentaGasto::class, 'cierre_venta_id');
    }
}