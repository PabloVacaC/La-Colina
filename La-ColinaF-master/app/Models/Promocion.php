<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Promocion extends Model
{
    protected $table = 'promociones';

    protected $fillable = [
        'nombre',
        'producto_id',
        'precio_promo',
        'fecha_inicio',
        'fecha_fin',
        'activa',
        'aplicar_a_todos',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin'    => 'datetime',
        'activa'       => 'boolean',
        'aplicar_a_todos'  => 'boolean',
    ];

    /* ============================
     * Relaciones
     * ============================ */

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    /* ============================
     * Helpers
     * ============================ */

    public function estaVigente()
    {
        if (!$this->activa) {
            return false;
        }

        $ahora = Carbon::now();

        return
            (!$this->fecha_inicio || $this->fecha_inicio <= $ahora) &&
            (!$this->fecha_fin || $this->fecha_fin >= $ahora);
    }

    /* ============================
     * Scopes útiles
     * ============================ */

    public function scopeActivas($query)
    {
        $ahora = Carbon::now();

        return $query
            ->where('activa', true)
            ->where(function ($q) use ($ahora) {
                $q->whereNull('fecha_inicio')
                  ->orWhere('fecha_inicio', '<=', $ahora);
            })
            ->where(function ($q) use ($ahora) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $ahora);
            });
    }

    /* ============================
     * Promo activa por producto
     * ============================ */

    public static function activaParaProducto($productoId)
    {
        return self::activas()
            ->where('producto_id', $productoId)
            ->first();
    }


    public function estaVencidaPorFecha()
    {
        return $this->fecha_fin && $this->fecha_fin->isPast();
    }


}