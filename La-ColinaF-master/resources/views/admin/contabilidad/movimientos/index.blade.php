@extends('adminlte::page')

@section('title', 'Movimientos contables')

@section('content')
<div class="container-fluid">


    {{-- =========================
        SELECTOR DE MES / AÑO
    ========================== --}}
    <form method="GET" class="card mb-4 mt-3">
        <div class="card-body row g-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label">Mes</label>
                <select name="mes" class="form-control">
                    @foreach([
                        1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
                        5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
                        9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
                    ] as $num => $nombre)
                        <option value="{{ $num }}"
                            {{ $mes == $num ? 'selected' : '' }}>
                            {{ $nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Año</label>
                <select name="anio" class="form-control">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}"
                            {{ $anio == $y ? 'selected' : '' }}>
                            {{ $y }}
                        </option>
                    @endfor
                </select>
            </div>

            <div class="col-md-3">
                <button class="btn btn-primary w-100">
                    Ver mes
                </button>
            </div>

        </div>
    </form>


    {{-- HEADER --}}
    <div class="d-flex justify-content-between mb-3">
        <h4>Movimientos contables</h4>

        <a href="{{ route('admin.contabilidad.movimientos.create') }}"
           class="btn btn-success">
            + Nuevo movimiento
        </a>
    </div>

    {{-- MENSAJE --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif





    {{-- =========================
        FILTROS DETALLADOS
    ========================== --}}
    <form method="GET" class="card mb-4">
        <input type="hidden" name="mes" value="{{ $mes }}">
        <input type="hidden" name="anio" value="{{ $anio }}">

        <div class="card-body row g-3 align-items-end">

            <div class="col-md-3">
                <label>Tipo</label>
                <select name="tipo" class="form-control">
                    <option value="">— Todos —</option>
                    <option value="ingreso" {{ request('tipo')=='ingreso'?'selected':'' }}>Ingreso</option>
                    <option value="gasto" {{ request('tipo')=='gasto'?'selected':'' }}>Gasto</option>
                </select>
            </div>

            <div class="col-md-3">
                <label>Categoría</label>
                <select name="categoria" class="form-control">
                    <option value="">— Todas —</option>
                    <option value="distribucion">Distribución</option>
                    <option value="produccion">Producción</option>
                    <option value="administrativo">Administrativo</option>
                    <option value="reinversion">Reinversión</option>
                    <option value="otro_ingreso">Otro ingreso</option>
                </select>
            </div>

            <div class="col-md-3">
                <label>Fecha</label>
                <input type="date"
                       name="fecha"
                       value="{{ request('fecha') }}"
                       class="form-control">
            </div>

            <div class="col-md-3">
                <button class="btn btn-primary w-100">
                    Filtrar
                </button>
            </div>

        </div>
    </form>

    {{-- =========================
        TABLA
    ========================== --}}
    <div class="card">
        <div class="card-body p-0">

            <div style="max-height: 500px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light sticky-top" style="top: 0; z-index: 1;">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Subcategoría</th>
                            <th>Descripción</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>

                    @forelse($movimientos as $mov)
                        <tr>
                            <td>{{ $mov->fecha->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge bg-{{ $mov->tipo=='ingreso'?'success':'danger' }}">
                                    {{ ucfirst($mov->tipo) }}
                                </span>
                            </td>
                            <td>{{ ucfirst($mov->categoria) }}</td>
                            <td>{{ $mov->subcategoria ?? '—' }}</td>
                            <td>{{ $mov->descripcion ?? '—' }}</td>
                            <td class="text-end">
                                {{ number_format($mov->monto, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No hay movimientos registrados
                            </td>
                        </tr>
                    @endforelse

                    </tbody>
                </table>
            
            </div>
            {{ $movimientos->withQueryString()->links() }}

        </div>
    </div>




    {{-- =========================
        RESUMEN CONTABLE
    ========================== --}}
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="alert alert-success">
                <strong>Ingresos:</strong>
                {{ number_format($totalIngresos,2) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert alert-danger">
                <strong>Gastos:</strong>
                {{ number_format($totalGastos,2) }}
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert alert-primary">
                <strong>Resultado:</strong>
                {{ number_format($totalIngresos - $totalGastos,2) }}
            </div>
        </div>
    </div>

    {{-- =========================
        DISTRIBUCIÓN
    ========================== --}}
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="alert alert-info">
                <strong>Ingresos distribución:</strong>
                {{ number_format($ingresosDistribucion, 2) }}
            </div>
        </div>

        <div class="col-md-4">
            <div class="alert alert-warning">
                <strong>Gastos distribución:</strong>
                {{ number_format($gastosDistribucion, 2) }}
            </div>
        </div>

        <div class="col-md-4">
            <div class="alert alert-primary">
                <strong>Resultado distribución:</strong>
                {{ number_format($resultadoDistribucion, 2) }}
            </div>
        </div>
    </div>

    {{-- =========================
        RESULTADO FINAL
    ========================== --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-dark text-center">
                <h5 class="mb-1">Resultado final del mes</h5>
                <h3 class="mb-0">
                    {{ number_format($resultadoFinal, 2) }}
                </h3>
                <small>
                    Ingresos totales:
                    {{ number_format($totalIngresosFinal, 2) }}
                    |
                    Gastos totales:
                    {{ number_format($totalGastosFinal, 2) }}
                </small>
            </div>
        </div>
    </div>


</div>
@endsection