<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class GastoFijo extends Model
{
    protected $table = 'gastos_fijos';

    protected $fillable = [
        'categoria',
        'subcategoria',
        'concepto',
        'monto',
        'dia_referencia',
        'activo',
    ];

    protected $casts = [
        'monto'          => 'decimal:2',
        'dia_referencia' => 'integer',
        'activo'         => 'boolean',
    ];

    /* ======================================================
     | SCOPES
     |======================================================*/

    /**
     * Solo gastos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Ordenados por día de referencia
     */
    public function scopeOrdenados($query)
    {
        return $query->orderBy('dia_referencia');
    }

    /* ======================================================
     | HELPERS
     |======================================================*/

    /**
     * Verifica si este gasto ya fue registrado
     * en movimientos_contables para un mes/año
     */
    public function yaRegistradoEnMes(int $mes, int $anio): bool
    {
        return \App\Models\MovimientoContable::where('tipo', 'gasto')
            ->where('categoria', $this->categoria)
            ->where('subcategoria', $this->subcategoria)
            ->where('descripcion', $this->concepto)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->exists();
    }

    /**
     * Fecha sugerida del gasto para el mes/año actual
     */
    public function fechaSugerida(int $mes = null, int $anio = null): Carbon
    {
        $mes  = $mes  ?? now()->month;
        $anio = $anio ?? now()->year;

        // Evita días inválidos (ej: 31 de febrero)
        $dia = min(
            $this->dia_referencia,
            Carbon::create($anio, $mes)->daysInMonth
        );

        return Carbon::create($anio, $mes, $dia);
    }
}