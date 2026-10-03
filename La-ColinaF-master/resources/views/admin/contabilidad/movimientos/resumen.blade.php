@extends('adminlte::page')

@section('title', 'Resumen contable mensual')

@section('content')
<div class="container-fluid">

    <h4 class="mb-4">
        Resumen contable –
        {{ $fecha->translatedFormat('F Y') }}
    </h4>

    {{-- ============================
         SELECTOR DE MES
    ============================ --}}
    <form method="GET" class="card mb-4">
        <div class="card-body row align-items-end">

            <div class="col-md-3">
                <label>Mes</label>
                <input type="month"
                       name="fecha"
                       value="{{ $fecha->format('Y-m') }}"
                       class="form-control">
            </div>

            <div class="col-md-2">
                <button class="btn btn-primary w-100">
                    Ver resumen
                </button>
            </div>

        </div>
    </form>

    {{-- ============================
         RESUMEN DISTRIBUIDORES
    ============================ --}}
    <h5 class="mb-3">Distribución</h5>

    @if(count($resumenDistribuidores))
    <div class="row">
        @foreach($resumenDistribuidores as $dist)
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">

                        <h6 class="mb-3 text-muted">
                            {{ $dist['nombre'] }}
                        </h6>

                        <div class="mb-2">
                            <strong class="text-success">Ingreso bruto:</strong>
                            {{ number_format($dist['ingreso'],2) }}
                        </div>

                        <div class="mb-2">
                            <strong class="text-danger">Gastos:</strong>
                            {{ number_format($dist['gastos'],2) }}
                        </div>

                        <hr>

                        <div>
                            <strong>Neto:</strong>
                            {{ number_format($dist['neto'],2) }}
                        </div>

                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @else
        <div class="alert alert-info">No hay registros de distribuidores para este mes.</div>
    @endif

    {{-- ============================
         TOTALES DISTRIBUCIÓN
    ============================ --}}
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="alert alert-success">
                <strong>Ingreso distribución:</strong><br>
                {{ number_format($totalIngresoDistribucion,2) }}
            </div>
        </div>

        <div class="col-md-4">
            <div class="alert alert-danger">
                <strong>Gastos distribución:</strong><br>
                {{ number_format($totalGastoDistribucion,2) }}
            </div>
        </div>

        <div class="col-md-4">
            <div class="alert alert-primary">
                <strong>Resultado distribución:</strong><br>
                {{ number_format($totalIngresoDistribucion - $totalGastoDistribucion, 2) }}
            </div>
        </div>
    </div>

    {{-- ============================
         OTROS INGRESOS / GASTOS
    ============================ --}}
    <h5 class="mt-5 mb-3">Otros movimientos</h5>
    <div class="row">
        <div class="col-md-6">
            <div class="alert alert-success">
                <strong>Otros ingresos:</strong><br>
                {{ number_format($otrosIngresos,2) }}
            </div>
        </div>

        <div class="col-md-6">
            <div class="alert alert-danger">
                <strong>Otros gastos:</strong><br>
                {{ number_format($otrosGastos,2) }}
            </div>
        </div>
    </div>

    {{-- ============================
         RESULTADO FINAL
    ============================ --}}
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="alert alert-dark text-center">
                <h5 class="mb-2">Dinero disponible total</h5>
                <h3 class="mb-0">{{ number_format($dineroDisponible,2) }}</h3>
            </div>
        </div>
    </div>

    {{-- ============================
         RESUMEN ANUAL (ENERO–DICIEMBRE)
    ============================ --}}
    <h5 class="mt-5 mb-3">Resumen anual {{ $fecha->year }}</h5>

    @if(count($resumenAnual))
    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered table-sm text-center mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>Mes</th>
                        <th>Ventas</th>
                        <th>Gastos distribución</th>
                        <th>Resultado distribución</th>
                        <th>Gastos administrativos</th>
                        <th>Gastos producción</th>
                        <th>Gastos reinversión</th>
                        <th>Total gastos</th>
                        <th>Utilidad neta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resumenAnual as $mes => $fila)
                        <tr>
                            <td class="text-left font-weight-bold">
                                {{ \Carbon\Carbon::create()->month($mes)->translatedFormat('F') }}
                            </td>
                            <td class="text-success">{{ number_format($fila['ventas'],2) }}</td>
                            <td class="text-danger">{{ number_format($fila['gastos_distribucion'],2) }}</td>
                            <td>{{ number_format($fila['resultado_distribucion'],2) }}</td>
                            <td class="text-danger">{{ number_format($fila['gastos_administrativos'],2) }}</td>
                            <td class="text-danger">{{ number_format($fila['gastos_produccion'],2) }}</td>
                            <td class="text-danger">{{ number_format($fila['gastos_reinversion'],2) }}</td>
                            <td class="text-danger font-weight-bold">{{ number_format($fila['total_gastos'],2) }}</td>
                            <td class="font-weight-bold {{ $fila['utilidad_neta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($fila['utilidad_neta'],2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="8" class="text-right">UTILIDAD NETA ANUAL</td>
                        <td class="{{ $utilidadNetaAnual >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($utilidadNetaAnual,2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @else
        <div class="alert alert-info">No hay datos contables para el año {{ $fecha->year }}.</div>
    @endif

    {{-- ============================
        GRÁFICAS ANUALES
    ============================ --}}
    <h5 class="mt-5 mb-3">Gráficas anuales</h5>

    <div class="row">

        {{-- Ventas vs Gastos --}}
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Ventas vs Gastos totales</h6>
                    <canvas id="ventasVsGastos"></canvas>
                </div>
            </div>
        </div>

        {{-- Utilidad neta --}}
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Utilidad neta mensual</h6>
                    <canvas id="utilidadNeta"></canvas>
                </div>
            </div>
        </div>

        {{-- Composición de gastos --}}
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Composición de gastos por categoría</h6>
                    <canvas id="gastosCategorias"></canvas>
                </div>
            </div>
        </div>

        {{-- Porcentaje utilidad sobre ventas --}}
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Porcentaje de utilidad sobre ventas</h6>
                    <canvas id="porcentajeUtilidad"></canvas>
                </div>
            </div>
        </div>

        {{-- Ingresos vs Gastos vs Utilidad --}}
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Ingresos, Gastos y Utilidad combinados</h6>
                    <canvas id="ingresosGastosUtilidad"></canvas>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    const resumenAnual = @json($resumenAnual);

    if(Object.keys(resumenAnual).length === 0) return;

    const ventas = Object.values(resumenAnual).map(m => m.ventas);
    const totalGastos = Object.values(resumenAnual).map(m => m.total_gastos);
    const utilidadNeta = Object.values(resumenAnual).map(m => m.utilidad_neta);

    const gastosDistribucion = Object.values(resumenAnual).map(m => m.gastos_distribucion);
    const gastosAdministrativos = Object.values(resumenAnual).map(m => m.gastos_administrativos);
    const gastosProduccion = Object.values(resumenAnual).map(m => m.gastos_produccion);
    const gastosReinversion = Object.values(resumenAnual).map(m => m.gastos_reinversion);

    const porcentajeUtilidad = utilidadNeta.map((u,i) => ventas[i] > 0 ? (u/ventas[i]*100).toFixed(2) : 0);

    // 1️⃣ Ventas vs Gastos
    new Chart(document.getElementById('ventasVsGastos'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [
                { label: 'Ventas', data: ventas, backgroundColor: 'rgba(40, 167, 69, 0.7)' },
                { label: 'Gastos', data: totalGastos, backgroundColor: 'rgba(220, 53, 69, 0.7)' }
            ]
        },
        options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    // 2️⃣ Utilidad neta
    new Chart(document.getElementById('utilidadNeta'), {
        type: 'line',
        data: { labels: meses, datasets:[{ label:'Utilidad neta', data: utilidadNeta, borderColor:'rgba(0,123,255,0.8)', backgroundColor:'rgba(0,123,255,0.2)', fill:true, tension:0.3 }] },
        options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    // 3️⃣ Composición de gastos
    new Chart(document.getElementById('gastosCategorias'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets:[
                {label:'Distribución', data:gastosDistribucion, backgroundColor:'rgba(40,167,69,0.7)'},
                {label:'Administrativo', data:gastosAdministrativos, backgroundColor:'rgba(255,193,7,0.7)'},
                {label:'Producción', data:gastosProduccion, backgroundColor:'rgba(23,162,184,0.7)'},
                {label:'Reinversión', data:gastosReinversion, backgroundColor:'rgba(108,117,125,0.7)'}
            ]
        },
        options:{ responsive:true, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ position:'top' } }, interaction:{ mode:'index', intersect:false }, stacked:true }
    });

    // 4️⃣ Porcentaje utilidad sobre ventas
    new Chart(document.getElementById('porcentajeUtilidad'), {
        type:'line',
        data:{ labels: meses, datasets:[{ label:'% Utilidad sobre ventas', data:porcentajeUtilidad, borderColor:'rgba(255,159,64,0.9)', backgroundColor:'rgba(255,159,64,0.3)', fill:true, tension:0.3 }] },
        options:{ responsive:true, scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback: v => v+'%' } } } }
    });

    // 5️⃣ Ingresos vs Gastos vs Utilidad
    new Chart(document.getElementById('ingresosGastosUtilidad'), {
        type:'bar',
        data:{ labels: meses, datasets:[
            { label:'Ventas', data:ventas, backgroundColor:'rgba(40,167,69,0.7)' },
            { label:'Gastos', data:totalGastos, backgroundColor:'rgba(220,53,69,0.7)' },
            { label:'Utilidad neta', data:utilidadNeta, type:'line', borderColor:'rgba(0,123,255,0.9)', backgroundColor:'rgba(0,123,255,0.2)', fill:false, tension:0.3 }
        ]},
        options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

});
</script>
@endpush