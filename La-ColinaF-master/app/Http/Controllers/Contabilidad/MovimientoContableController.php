<?php

namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MovimientoContable;
use Carbon\Carbon;
use App\Models\GastoFijo;
use App\Models\CierreVenta;

class MovimientoContableController extends Controller
{
    /**
     * Historial de movimientos contables
     */
    public function index(Request $request)
    {
        // =============================
        // MES / AÑO ACTIVO
        // =============================
        $mes  = $request->filled('mes')
            ? (int) $request->mes
            : now()->month;

        $anio = $request->filled('anio')
            ? (int) $request->anio
            : now()->year;

        // =============================
        // QUERY BASE MOVIMIENTOS
        // =============================
        $query = MovimientoContable::query()
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc');

        // =============================
        // FILTROS ADICIONALES
        // =============================
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        // =============================
        // TOTALES MOVIMIENTOS CONTABLES
        // =============================
        $totalIngresos = (clone $query)
            ->where('tipo', 'ingreso')
            ->sum('monto');

        $totalGastos = (clone $query)
            ->where('tipo', 'gasto')
            ->sum('monto');

        // =============================
        // QUERY BASE CIERRES DE VENTA
        // =============================
        $cierresQuery = CierreVenta::query()
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio);

        // =============================
        // TOTALES DISTRIBUCIÓN
        // =============================
        $ingresosDistribucion = (clone $cierresQuery)
            ->sum('ingreso_bruto');

        $gastosDistribucion = (clone $cierresQuery)
            ->sum('total_gastos_distribucion');

        $resultadoDistribucion =
            $ingresosDistribucion - $gastosDistribucion;

        // =============================
        // RESULTADO FINAL GENERAL
        // =============================
        $totalIngresosFinal =
            $totalIngresos + $ingresosDistribucion;

        $totalGastosFinal =
            $totalGastos + $gastosDistribucion;

        $resultadoFinal =
            $totalIngresosFinal - $totalGastosFinal;

        // =============================
        // PAGINACIÓN
        // =============================
        $movimientos = $query->paginate(20);

        return view('admin.contabilidad.movimientos.index', compact(
            'movimientos',
            'mes',
            'anio',
            'totalIngresos',
            'totalGastos',
            'ingresosDistribucion',
            'gastosDistribucion',
            'resultadoDistribucion',
            'totalIngresosFinal',
            'totalGastosFinal',
            'resultadoFinal'
        ));
    }

    /**
     * Formulario de registro
     */


    public function create()
    {
        $mes  = now()->month;
        $anio = now()->year;

        $gastosFijos = GastoFijo::ordenados()
            ->get()
            ->map(function ($gasto) use ($mes, $anio) {

                // ¿Ya fue registrado este mes?
                $gasto->ya_registrado = $gasto->yaRegistradoEnMes($mes, $anio);

                // Fecha sugerida (solo informativa)
                $gasto->fecha_sugerida = $gasto
                    ->fechaSugerida($mes, $anio)
                    ->toDateString();

                // ¿Se puede cargar al formulario?
                $gasto->puede_cargar =
                    $gasto->activo &&
                    !$gasto->ya_registrado;

                return $gasto;
            });

        return view('admin.contabilidad.movimientos.create', compact(
            'gastosFijos'
        ));
    }


    public function registrarGastoFijo(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|exists:gastos_fijos,id',
        ]);

        $gasto = \App\Models\GastoFijo::findOrFail($request->gasto_fijo_id);

        $fecha = $gasto->fechaSugerida();

        MovimientoContable::create([
            'tipo'         => 'gasto',
            'categoria'    => $gasto->categoria,
            'subcategoria' => $gasto->subcategoria,
            'descripcion'  => $gasto->concepto,
            'monto'        => $gasto->monto,
            'fecha'        => $fecha,
            'mes'          => $fecha->month,
            'anio'         => $fecha->year,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Gasto fijo registrado correctamente');
    }

    /**
     * Guardar movimiento contable
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo'         => 'required|in:ingreso,gasto',
            'categoria'    => 'required|string|max:50',
            'subcategoria' => 'nullable|string|max:100',
            'descripcion'  => 'nullable|string|max:255',
            'monto'        => 'required|numeric|min:0.01',
            'fecha'        => 'required|date',
        ]);

        $fecha = Carbon::parse($request->fecha);

        MovimientoContable::create([
            'tipo'         => $request->tipo,
            'categoria'    => $request->categoria,
            'subcategoria' => $request->subcategoria,
            'descripcion'  => $request->descripcion,
            'monto'        => $request->monto,
            'fecha'        => $fecha,
            'mes'          => $fecha->month,
            'anio'         => $fecha->year,
        ]);

        return redirect()
            ->route('admin.contabilidad.movimientos.index')
            ->with('success', 'Movimiento contable registrado correctamente');
    }


    public function resumen(Request $request)
    {
        $fecha = $request->fecha
            ? Carbon::parse($request->fecha)
            : now();

        $mes  = $fecha->month;
        $anio = $fecha->year;

        // =============================
        // DISTRIBUIDORES + CIERRES
        // =============================
        $distribuidores = \App\Models\Motoquero::with(['cierresVentas' => function ($q) use ($mes, $anio) {
            $q->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio);
        }])->get();

        $resumenDistribuidores = $distribuidores->map(function ($d) {
            $ingreso = $d->cierresVentas->sum('ingreso_bruto');
            $gastos  = $d->cierresVentas->sum('total_gastos_distribucion');

            return [
                'nombre'  => $d->nombres.' '.$d->apellidos,
                'ingreso' => $ingreso,
                'gastos'  => $gastos,
                'neto'    => $ingreso - $gastos,
            ];
        });

        $totalIngresoDistribucion = $resumenDistribuidores->sum('ingreso');
        $totalGastoDistribucion   = $resumenDistribuidores->sum('gastos');

        // =============================
        // OTROS INGRESOS / GASTOS
        // =============================
        $otrosIngresos = MovimientoContable::ingresos()
            ->delMes($mes, $anio)
            ->where('categoria', '!=', 'distribucion')
            ->sum('monto');

        $otrosGastos = MovimientoContable::gastos()
            ->delMes($mes, $anio)
            ->sum('monto');

        $ingresosTotales = $totalIngresoDistribucion + $otrosIngresos;
        $gastosTotales   = $totalGastoDistribucion + $otrosGastos;

        $dineroDisponible = $ingresosTotales - $gastosTotales;


        // =============================
        // RESUMEN ANUAL (MES A MES)
        // =============================
        $resumenAnual = [];
        $utilidadNetaAnual = 0;

        for ($m = 1; $m <= 12; $m++) {

            // 🟢 INGRESOS POR VENTAS (solo cierres)
            $ventas = CierreVenta::whereMonth('fecha', $m)
                ->whereYear('fecha', $anio)
                ->sum('ingreso_bruto');

            // 🔴 GASTOS DE DISTRIBUCIÓN
            $gastosDistribucionMes = CierreVenta::whereMonth('fecha', $m)
                ->whereYear('fecha', $anio)
                ->sum('total_gastos_distribucion');

            // 🔴 GASTOS ADMINISTRATIVOS
            $gastosAdministrativos = MovimientoContable::gastos()
                ->where('categoria', 'administrativo')
                ->delMes($m, $anio)
                ->sum('monto');

            // 🔴 GASTOS PRODUCCIÓN
            $gastosProduccion = MovimientoContable::gastos()
                ->where('categoria', 'produccion')
                ->delMes($m, $anio)
                ->sum('monto');

            // 🔴 GASTOS REINVERSIÓN
            $gastosReinversion = MovimientoContable::gastos()
                ->where('categoria', 'reinversion')
                ->delMes($m, $anio)
                ->sum('monto');

            $totalGastosMes =
                $gastosDistribucionMes +
                $gastosAdministrativos +
                $gastosProduccion +
                $gastosReinversion;

            $utilidadNetaMes = $ventas - $totalGastosMes;

            $resumenAnual[$m] = [
                'ventas' => $ventas,
                'gastos_distribucion' => $gastosDistribucionMes,
                'resultado_distribucion' =>
                    $ventas - $gastosDistribucionMes,
                'gastos_administrativos' => $gastosAdministrativos,
                'gastos_produccion' => $gastosProduccion,
                'gastos_reinversion' => $gastosReinversion,
                'total_gastos' => $totalGastosMes,
                'utilidad_neta' => $utilidadNetaMes,
            ];

            $utilidadNetaAnual += $utilidadNetaMes;
        }



        return view('admin.contabilidad.movimientos.resumen', compact(
            'fecha',
            'resumenDistribuidores',
            'totalIngresoDistribucion',
            'totalGastoDistribucion',
            'otrosIngresos',
            'otrosGastos',
            'ingresosTotales',
            'gastosTotales',
            'resumenAnual',
            'utilidadNetaAnual',
            'dineroDisponible'
        ));
    }


    public function storeGastoFijo(Request $request)
    {
        $request->validate([
            'categoria'       => 'required|string',
            'subcategoria'    => 'required|string',
            'concepto'        => 'required|string',
            'monto'           => 'required|numeric|min:0',
            'dia_referencia'  => 'required|integer|min:1|max:31',
        ]);

        GastoFijo::create([
            'categoria'       => $request->categoria,
            'subcategoria'    => $request->subcategoria,
            'concepto'        => $request->concepto,
            'monto'           => $request->monto,
            'dia_referencia'  => $request->dia_referencia,
            'activo'          => true,
        ]);

        return back()->with('success', 'Gasto fijo creado correctamente');
    }


    public function updateGastoFijo(Request $request, $id)
    {
        $gasto = GastoFijo::findOrFail($id);

        $request->validate([
            'categoria'       => 'required|string',
            'subcategoria'    => 'required|string',
            'concepto'        => 'required|string',
            'monto'           => 'required|numeric|min:0',
            'dia_referencia'  => 'required|integer|min:1|max:31',
        ]);

        $gasto->update($request->all());

        return back()->with('success', 'Gasto fijo actualizado');
    }


    public function toggleGastoFijo($id)
    {
        $gasto = GastoFijo::findOrFail($id);

        $gasto->activo = ! $gasto->activo;
        $gasto->save();

        return back();
    }




}