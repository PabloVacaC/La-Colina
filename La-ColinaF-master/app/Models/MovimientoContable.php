<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MovimientoContable extends Model
{
    protected $table = 'movimientos_contables';

    protected $fillable = [
        'tipo',
        'categoria',
        'subcategoria',
        'descripcion',
        'monto',
        'fecha',
        'mes',
        'anio',
        'referencia_id',
        'referencia_tipo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    /* ============================
     * SCOPES ÚTILES
     * ============================ */

    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeGastos($query)
    {
        return $query->where('tipo', 'gasto');
    }

    public function scopeDelMes($query, $mes, $anio)
    {
        return $query->where('mes', $mes)
                     ->where('anio', $anio);
    }

    public function scopeCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /* ============================
     * HELPERS
     * ============================ */

    public static function ingresoBrutoMes($mes, $anio)
    {
        return self::ingresos()
            ->delMes($mes, $anio)
            ->sum('monto');
    }

    public static function gastosDistribucionMes($mes, $anio)
    {
        return self::gastos()
            ->categoria('distribucion')
            ->delMes($mes, $anio)
            ->sum('monto');
    }

    public static function ingresoOperativoMes($mes, $anio)
    {
        return self::ingresoBrutoMes($mes, $anio)
            - self::gastosDistribucionMes($mes, $anio);
    }
}
