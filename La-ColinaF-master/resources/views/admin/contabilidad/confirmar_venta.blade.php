@extends('adminlte::page')

@section('title', 'Confirmar venta')

@section('content')
<div class="container-fluid">

    <h4 class="mb-4">Confirmación de venta diaria</h4>

    
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    

    {{-- =======================
        SELECTOR DISTRIBUIDOR / FECHA
    ======================= --}}
    <form method="GET" action="{{ route('admin.contabilidad.confirmar_venta.create') }}" class="card mb-4">
        <div class="card-body row g-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label">Distribuidor</label>
                <select name="distribuidor_id" class="form-control" required>
                    <option value="">— Seleccionar —</option>
                    @foreach($distribuidores as $dist)
                        <option value="{{ $dist->id }}"
                            {{ ($distribuidorId ?? '' ) == $dist->id ? 'selected' : '' }}>
                            {{ $dist->nombres }} {{ $dist->apellidos }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date"
                       name="fecha"
                       class="form-control"
                       value="{{ $fecha ?? now()->toDateString() }}"
                       required>
            </div>

            <div class="col-md-2">
                <button class="btn btn-primary w-100">
                    Buscar
                </button>
            </div>

        </div>
    </form>

    @if(isset($pedidos))

    {{-- =======================
        RESUMEN DE PEDIDOS
    ======================= --}}
    <div class="card mb-4">
        <div class="card-body table-responsive">

            <h5 class="mb-3">Pedidos del día</h5>

            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Método de pago</th>
                        <th>Hora</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pedidos as $pedido)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>#{{ $pedido->cliente->nombre ?? 'Sin cliente' }}</td>
                            <td>{{ number_format($pedido->total_precio, 2) }}</td>

                            <td>

                                @if($pedido->metodo_pago == 'QR')

                                    <span class="badge badge-success">
                                        QR
                                    </span>

                                @elseif($pedido->metodo_pago == 'Efectivo')

                                    <span class="badge badge-primary">
                                        Efectivo
                                    </span>

                                @else

                                    <span class="badge badge-secondary">
                                        No definido
                                    </span>

                                @endif

                            </td>


                            <td>{{ $pedido->updated_at->format('H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No hay pedidos para esta fecha
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="row text-end">
                <div class="col-md-4">
                    <strong>Total efectivo:</strong><br>
                    {{ number_format($ingresoEfectivo, 2) }} Bs
                </div>
                <div class="col-md-4">
                    <strong>Total QR:</strong><br>
                    {{ number_format($ingresoQR, 2) }} Bs
                </div>
                <div class="col-md-4">
                    <strong>Ingreso bruto:</strong><br>
                    {{ number_format($ingresoBruto, 2) }} Bs
                </div>
            </div>

        </div>
    </div>


    @if($cierreExistente)
        <div class="alert alert-success">
            ✅ La venta de este distribuidor para esta fecha ya fue confirmada.
        </div>
    @endif


    {{-- =======================
        CONFIRMACIÓN + GASTOS
    ======================= --}}
    <form method="POST" action="{{ route('admin.contabilidad.confirmar_venta.store') }}" class="card">
             <input type="hidden" name="fecha" value="{{ $fecha }}">
        <input type="hidden" name="distribuidor_id" value="{{ $distribuidorId }}">
        <input type="hidden" name="ingreso_bruto" value="{{ $ingresoBruto }}">
        <input type="hidden" name="ingreso_efectivo" value="{{ $ingresoEfectivo }}">
        <input type="hidden" name="ingreso_qr" value="{{ $ingresoQR }}">

        <div class="card-body">

            {{-- ==============================================
                📦 RESUMEN DETALLADO DE PRODUCTOS VENDIDOS
            ============================================== --}}
            <div class="card bg-light border-info mb-4">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-boxes"></i> Resumen de Productos Vendidos del Día</h6>
                    <span class="badge badge-light text-dark">{{ $pedidos->count() }} entregas</span>
                </div>
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col-md-2 col-6 border-right py-2">
                            <span class="text-muted d-block small font-weight-bold">BOTELLONES NORMALES</span>
                            <span class="h4 font-weight-bold text-primary">{{ $vendidosRegular }}</span>
                            <small class="d-block text-muted">Agua Regular</small>
                        </div>
                        <div class="col-md-2 col-6 border-right py-2">
                            <span class="text-muted d-block small font-weight-bold">ALCALINAS</span>
                            <span class="h4 font-weight-bold text-info">{{ $vendidosAlcalina }}</span>
                            <small class="d-block text-muted">Agua Alcalina</small>
                        </div>
                        <div class="col-md-2 col-6 border-right py-2">
                            <span class="text-muted d-block small font-weight-bold">ENTEROS NORMALES</span>
                            <span class="h4 font-weight-bold text-success">{{ $vendidosEnteroRegular }}</span>
                            <small class="d-block text-muted">Botellón + Regular</small>
                        </div>
                        <div class="col-md-2 col-6 border-right py-2">
                            <span class="text-muted d-block small font-weight-bold">ENTEROS ALCALINAS</span>
                            <span class="h4 font-weight-bold text-warning">{{ $vendidosEnteroAlcalina }}</span>
                            <small class="d-block text-muted">Botellón + Alcalina</small>
                        </div>
                        <div class="col-md-2 col-6 border-right py-2">
                            <span class="text-muted d-block small font-weight-bold">DISPENSERS</span>
                            <span class="h4 font-weight-bold text-purple" style="color: #6f42c1;">{{ $vendidosDispensers }}</span>
                            <small class="d-block text-muted">Mesa / Bombitas</small>
                        </div>
                        <div class="col-md-2 col-6 py-2">
                            <span class="text-muted d-block small font-weight-bold">TOTAL VENDIDOS</span>
                            <span class="h4 font-weight-bold text-dark">{{ $vendidosRegular + $vendidosAlcalina + $vendidosEnteroRegular + $vendidosEnteroAlcalina + $vendidosDispensers }}</span>
                            <small class="d-block text-muted">Unidades</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==============================================
                ⛽ GASTOS DE DISTRIBUCIÓN (COMBUSTIBLES Y OTROS)
            ============================================== --}}
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0 text-dark"><i class="fas fa-receipt text-danger"></i> Gastos del Día</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark font-weight-bold" id="agregar-combustible">
                        <i class="fas fa-gas-pump text-danger"></i> + Agregar Combustible
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold ml-2" id="agregar-otro">
                        <i class="fas fa-plus-circle text-primary"></i> + Agregar Otro Gasto
                    </button>
                </div>
            </div>

            <table class="table table-bordered table-hover" id="tabla-gastos">
                <thead class="table-light">
                    <tr>
                        <th>Concepto / Detalle del Gasto</th>
                        <th width="220">Monto (Bs.)</th>
                        <th width="60" class="text-center">Quitar</th>
                    </tr>
                </thead>
                <tbody>
                    @if($cierreExistente && $cierreExistente->gastos && $cierreExistente->gastos->count() > 0)
                        @foreach($cierreExistente->gastos as $idx => $g)
                            <tr>
                                <td>
                                    <input type="text"
                                           name="gastos[{{ $idx }}][concepto]"
                                           class="form-control concepto-gasto"
                                           value="{{ $g->concepto }}"
                                           placeholder="Ej: Combustible, Pinchazo, etc." required>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">Bs.</span></div>
                                        <input type="number"
                                               step="0.01"
                                               name="gastos[{{ $idx }}][monto]"
                                               class="form-control monto-gasto"
                                               value="{{ $g->monto }}" required>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger eliminar-fila">✕</button>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>
                                <input type="text"
                                       name="gastos[0][concepto]"
                                       class="form-control concepto-gasto"
                                       value="Combustible"
                                       placeholder="Ej: Combustible">
                            </td>
                            <td>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Bs.</span></div>
                                    <input type="number"
                                           step="0.01"
                                           name="gastos[0][monto]"
                                           class="form-control monto-gasto"
                                           value="0">
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-danger eliminar-fila">✕</button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="row mt-4">

                {{-- ================= BOTELLONES ================= --}}
                <div class="col-md-6">

                    <div class="card border-primary shadow-sm">
                        <div class="card-body">

                            <h6 class="mb-3 text-primary font-weight-bold"><i class="fas fa-clipboard-check"></i> <b>Control de Inventario Botellones</b></h6>

                            <table class="table table-sm table-bordered text-center mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th></th>
                                        <th>Regular</th>
                                        <th>Alcalina</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><b>Despachado</b></td>
                                        <td>{{ $regularDespachado }}</td>
                                        <td>{{ $alcalinaDespachado }}</td>
                                    </tr>

                                    <tr class="table-warning">
                                        <td><b>Vendido</b></td>
                                        <td>{{ $vendidosRegular }}</td>
                                        <td>{{ $vendidosAlcalina }}</td>
                                    </tr>

                                    <tr class="{{ ($restanteRegular < 0 || $restanteAlcalina < 0) ? 'table-danger font-weight-bold' : 'table-success' }}">
                                        <td><b>Restante</b></td>
                                        <td>{{ $restanteRegular }}</td>
                                        <td>{{ $restanteAlcalina }}</td>
                                    </tr>
                                </tbody>
                            </table>

                        </div>
                    </div>

                </div>


                {{-- ================= FINANZAS ================= --}}
                <div class="col-md-6">
                    <div class="card border-success shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3 text-success font-weight-bold"><i class="fas fa-wallet"></i> <b>Liquidación Financiera</b></h6>

                            <div class="d-flex justify-content-between border-bottom py-1">
                                <span>Total Efectivo Recaudado:</span>
                                <strong>Bs. {{ number_format($ingresoEfectivo, 2) }}</strong>
                            </div>

                            <div class="d-flex justify-content-between border-bottom py-1 text-primary">
                                <span>Total en QR / Transferencia:</span>
                                <strong>Bs. {{ number_format($ingresoQR, 2) }}</strong>
                            </div>

                            <div class="d-flex justify-content-between border-bottom py-1 text-dark">
                                <span>Ingreso Bruto Total:</span>
                                <strong>Bs. {{ number_format($ingresoBruto, 2) }}</strong>
                            </div>

                            <div class="d-flex justify-content-between border-bottom py-1 text-danger">
                                <span>Total de Gastos (Combustible / Otros):</span>
                                <strong>- Bs. <span id="total-gastos">0.00</span></strong>
                            </div>

                            <div class="d-flex justify-content-between pt-2">
                                <span class="h6 font-weight-bold text-success mb-0">Total a Entregar en Efectivo:</span>
                                <span class="h5 font-weight-bold text-success mb-0">Bs. <span id="efectivo-a-entregar">{{ number_format($ingresoEfectivo, 2) }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>
                <button type="button" class="btn btn-info font-weight-bold mr-2" onclick="imprimirReporte()">
                    <i class="fas fa-print"></i> Descargar / Imprimir Reporte
                </button>
                <a href="#" id="btn-whatsapp-empresa" target="_blank" class="btn btn-success font-weight-bold">
                    <i class="fab fa-whatsapp"></i> Enviar a WhatsApp Empresa
                </a>
            </div>

            <div>
               @if($cierreExistente)
                    <button class="btn btn-warning font-weight-bold">
                        <i class="fas fa-sync-alt"></i> Actualizar cierre de venta
                    </button>
                @else
                    <button class="btn btn-success font-weight-bold">
                        <i class="fas fa-check-circle"></i> Confirmar venta del día
                    </button>
                @endif
            </div>

        </div>

    </form>

    {{-- ==============================================
        🖨️ CONTENEDOR DE IMPRESIÓN DEL REPORTE
    ============================================== --}}
    <div id="area-impresion" class="d-none">
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 750px; margin: 0 auto; color: #333;">
            <div style="text-align: center; border-bottom: 2px solid #0056b3; padding-bottom: 12px; margin-bottom: 15px;">
                <h2 style="margin: 0; color: #0056b3;">{{ $configuracion->nombre ?? 'AGUA PURIFICADA LA COLINA' }}</h2>
                <p style="margin: 3px 0; font-size: 14px; color: #666;">{{ $configuracion->descripcion ?? 'Reporte Diario de Ventas y Liquidación' }}</p>
                <p style="margin: 2px 0; font-size: 13px;">Fecha: <b>{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</b> | Teléfono: {{ $telefonoEmpresa }}</p>
            </div>

            @php
                $distribuidorSeleccionado = $distribuidores->firstWhere('id', $distribuidorId);
            @endphp

            <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                <p style="margin: 3px 0;"><b>Distribuidor:</b> {{ $distribuidorSeleccionado ? ($distribuidorSeleccionado->nombres . ' ' . $distribuidorSeleccionado->apellidos) : 'No seleccionado' }}</p>
                <p style="margin: 3px 0;"><b>Placa / Vehículo:</b> {{ $distribuidorSeleccionado->placa ?? 'N/A' }} | <b>Total Pedidos Entregados:</b> {{ $pedidos->count() }}</p>
            </div>

            <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 4px; color: #0056b3; margin-top: 15px;">1. Productos Vendidos</h4>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 14px;">
                <thead>
                    <tr style="background: #e9ecef;">
                        <th style="border: 1px solid #ccc; padding: 6px; text-align: left;">Producto / Categoría</th>
                        <th style="border: 1px solid #ccc; padding: 6px; text-align: center;">Cantidad Vendida</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="border: 1px solid #ccc; padding: 6px;">Botellones Normales (Agua Regular)</td><td style="border: 1px solid #ccc; padding: 6px; text-align: center;"><b>{{ $vendidosRegular }}</b></td></tr>
                    <tr><td style="border: 1px solid #ccc; padding: 6px;">Alcalinas (Agua Alcalina)</td><td style="border: 1px solid #ccc; padding: 6px; text-align: center;"><b>{{ $vendidosAlcalina }}</b></td></tr>
                    <tr><td style="border: 1px solid #ccc; padding: 6px;">Enteros Normales (Botellón + Regular)</td><td style="border: 1px solid #ccc; padding: 6px; text-align: center;"><b>{{ $vendidosEnteroRegular }}</b></td></tr>
                    <tr><td style="border: 1px solid #ccc; padding: 6px;">Enteros Alcalinas (Botellón + Alcalina)</td><td style="border: 1px solid #ccc; padding: 6px; text-align: center;"><b>{{ $vendidosEnteroAlcalina }}</b></td></tr>
                    <tr><td style="border: 1px solid #ccc; padding: 6px;">Dispensers (Mesa / Bombitas)</td><td style="border: 1px solid #ccc; padding: 6px; text-align: center;"><b>{{ $vendidosDispensers }}</b></td></tr>
                    <tr style="background: #f1f3f5; font-weight: bold;">
                        <td style="border: 1px solid #ccc; padding: 6px;">TOTAL PRODUCTOS</td>
                        <td style="border: 1px solid #ccc; padding: 6px; text-align: center;">{{ $vendidosRegular + $vendidosAlcalina + $vendidosEnteroRegular + $vendidosEnteroAlcalina + $vendidosDispensers }}</td>
                    </tr>
                </tbody>
            </table>

            <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 4px; color: #0056b3;">2. Gastos de Distribución</h4>
            <div id="print-gastos-lista" style="margin-bottom: 15px;"></div>

            <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 4px; color: #0056b3;">3. Resumen Financiero y Liquidación</h4>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 14px;">
                <tr><td style="border: 1px solid #ccc; padding: 6px;">Total Recaudado en Efectivo:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><b>Bs. {{ number_format($ingresoEfectivo, 2) }}</b></td></tr>
                <tr><td style="border: 1px solid #ccc; padding: 6px;">Total Pagado por QR / Transferencia:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><b>Bs. {{ number_format($ingresoQR, 2) }}</b></td></tr>
                <tr><td style="border: 1px solid #ccc; padding: 6px;">Total Ingreso Bruto:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><b>Bs. {{ number_format($ingresoBruto, 2) }}</b></td></tr>
                <tr><td style="border: 1px solid #ccc; padding: 6px; color: #c00;">Total de Gastos Deductibles:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right; color: #c00;"><b>- Bs. <span id="print-total-gastos">0.00</span></b></td></tr>
                <tr style="background: #d4edda; font-size: 16px; font-weight: bold; color: #155724;">
                    <td style="border: 1px solid #28a745; padding: 8px;">TOTAL A ENTREGAR EN EFECTIVO:</td>
                    <td style="border: 1px solid #28a745; padding: 8px; text-align: right;">Bs. <span id="print-efectivo-entregar">0.00</span></td>
                </tr>
            </table>

            <div style="display: flex; justify-content: space-between; margin-top: 50px; text-align: center;">
                <div style="width: 40%; border-top: 1px solid #333; padding-top: 6px;">
                    <p style="margin: 0; font-size: 13px;">Firma del Distribuidor</p>
                </div>
                <div style="width: 40%; border-top: 1px solid #333; padding-top: 6px;">
                    <p style="margin: 0; font-size: 13px;">Firma de Administración</p>
                </div>
            </div>
        </div>
    </div>

    @endif

</div>
@endsection

{{-- =======================
    JS CORREGIDO Y MEJORADO
======================= --}}
@section('js')
<script>
let indexGasto = {{ ($cierreExistente && $cierreExistente->gastos) ? $cierreExistente->gastos->count() : 1 }};

const ingresoEfectivo = {{ $ingresoEfectivo ?? 0 }};
const ingresoQR       = {{ $ingresoQR ?? 0 }};
const ingresoBruto    = {{ $ingresoBruto ?? 0 }};

const vendidosRegular        = {{ $vendidosRegular ?? 0 }};
const vendidosAlcalina       = {{ $vendidosAlcalina ?? 0 }};
const vendidosEnteroRegular  = {{ $vendidosEnteroRegular ?? 0 }};
const vendidosEnteroAlcalina = {{ $vendidosEnteroAlcalina ?? 0 }};
const vendidosDispensers     = {{ $vendidosDispensers ?? 0 }};

const fechaReporte    = "{{ \Carbon\Carbon::parse($fecha ?? now())->format('d/m/Y') }}";
const distribuidorNom = "{{ isset($distribuidorSeleccionado) && $distribuidorSeleccionado ? ($distribuidorSeleccionado->nombres . ' ' . $distribuidorSeleccionado->apellidos) : 'Distribuidor' }}";
const telefonoEmpresa = "{{ $telefonoEmpresa ?? '59163524474' }}";
const nombreEmpresa   = "{{ $configuracion->nombre ?? 'La Colina' }}";

// Agregar fila de COMBUSTIBLE
document.getElementById('agregar-combustible')?.addEventListener('click', () => {
    agregarFilaGasto('Combustible');
});

// Agregar fila de OTRO GASTO
document.getElementById('agregar-otro')?.addEventListener('click', () => {
    agregarFilaGasto('');
});

function agregarFilaGasto(conceptoPredef = '') {
    const tbody = document.querySelector('#tabla-gastos tbody');
    const fila = document.createElement('tr');
    fila.innerHTML = `
        <td>
            <input type="text"
                   name="gastos[${indexGasto}][concepto]"
                   class="form-control concepto-gasto"
                   value="${conceptoPredef}"
                   placeholder="Ej: Combustible, Pinchazo, Peaje..." required>
        </td>
        <td>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text">Bs.</span></div>
                <input type="number"
                       step="0.01"
                       name="gastos[${indexGasto}][monto]"
                       class="form-control monto-gasto"
                       value="0" required>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger eliminar-fila">✕</button>
        </td>
    `;
    tbody.appendChild(fila);
    indexGasto++;
    calcularTotales();
}

// Eliminar fila de gasto
document.addEventListener('click', e => {
    if (e.target.classList.contains('eliminar-fila')) {
        e.target.closest('tr').remove();
        calcularTotales();
    }
});

// Recalcular al cambiar monto o concepto
document.addEventListener('input', e => {
    if (e.target.classList.contains('monto-gasto') || e.target.classList.contains('concepto-gasto')) {
        calcularTotales();
    }
});

function calcularTotales() {
    let totalGastos = 0;
    let itemsGastos = [];

    document.querySelectorAll('#tabla-gastos tbody tr').forEach(row => {
        const conc = row.querySelector('.concepto-gasto')?.value.trim() || 'Gasto';
        const mont = parseFloat(row.querySelector('.monto-gasto')?.value || 0);
        if (mont > 0) {
            totalGastos += mont;
            itemsGastos.push(`• ${conc}: Bs. ${mont.toFixed(2)}`);
        }
    });

    const efectivoEntregar = Math.max(0, ingresoEfectivo - totalGastos);

    document.getElementById('total-gastos').innerText = totalGastos.toFixed(2);
    document.getElementById('efectivo-a-entregar').innerText = efectivoEntregar.toFixed(2);

    // Actualizar datos del área de impresión
    const printTotGastos = document.getElementById('print-total-gastos');
    if (printTotGastos) printTotGastos.innerText = totalGastos.toFixed(2);

    const printEfecEntregar = document.getElementById('print-efectivo-entregar');
    if (printEfecEntregar) printEfecEntregar.innerText = efectivoEntregar.toFixed(2);

    const printGastosLista = document.getElementById('print-gastos-lista');
    if (printGastosLista) {
        if (itemsGastos.length > 0) {
            let htmlG = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
            document.querySelectorAll('#tabla-gastos tbody tr').forEach(row => {
                const conc = row.querySelector('.concepto-gasto')?.value.trim() || 'Gasto';
                const mont = parseFloat(row.querySelector('.monto-gasto')?.value || 0);
                if (mont > 0) {
                    htmlG += `<tr><td style="border: 1px solid #ccc; padding: 4px 6px;">${conc}</td><td style="border: 1px solid #ccc; padding: 4px 6px; text-align: right;">Bs. ${mont.toFixed(2)}</td></tr>`;
                }
            });
            htmlG += '</table>';
            printGastosLista.innerHTML = htmlG;
        } else {
            printGastosLista.innerHTML = '<p style="color: #888; font-style: italic;">Sin gastos registrados</p>';
        }
    }

    // Actualizar Link de WhatsApp
    actualizarBotonWhatsApp(totalGastos, efectivoEntregar, itemsGastos);
}

function actualizarBotonWhatsApp(totalGastos, efectivoEntregar, itemsGastos) {
    const btnWa = document.getElementById('btn-whatsapp-empresa');
    if (!btnWa) return;

    const gastosTexto = itemsGastos.length > 0 ? itemsGastos.join('\n') : '• Sin gastos registrados';

    const mensaje = `📋 *CIERRE DE VENTAS DEL DÍA*\n` +
        `📅 *Fecha:* ${fechaReporte}\n` +
        `🛵 *Distribuidor:* ${distribuidorNom}\n` +
        `🏢 *Empresa:* ${nombreEmpresa}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `📦 *PRODUCTOS VENDIDOS:*\n` +
        `• Botellones normales: ${vendidosRegular}\n` +
        `• Alcalinas: ${vendidosAlcalina}\n` +
        `• Enteros normales: ${vendidosEnteroRegular}\n` +
        `• Enteros alcalinas: ${vendidosEnteroAlcalina}\n` +
        `• Dispensers: ${vendidosDispensers}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `💰 *RESUMEN DE INGRESOS:*\n` +
        `💵 Total Efectivo: Bs. ${ingresoEfectivo.toFixed(2)}\n` +
        `📱 Total en QR: Bs. ${ingresoQR.toFixed(2)}\n` +
        `💎 Total Ingresos: Bs. ${ingresoBruto.toFixed(2)}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `⛽ *GASTOS REGISTRADOS:*\n` +
        `${gastosTexto}\n` +
        `🔻 Total Gastos: Bs. ${totalGastos.toFixed(2)}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `💵 *TOTAL A ENTREGAR EN EFECTIVO:*\n` +
        `👉 *Bs. ${efectivoEntregar.toFixed(2)}*\n` +
        `━━━━━━━━━━━━━━━━━━━━`;

    btnWa.href = `https://wa.me/${telefonoEmpresa}?text=${encodeURIComponent(mensaje)}`;
}

// Inicializar cálculos al cargar
document.addEventListener('DOMContentLoaded', () => {
    calcularTotales();
});

// Imprimir / Descargar reporte
function imprimirReporte() {
    calcularTotales();
    const contenido = document.getElementById('area-impresion').innerHTML;
    const ventana = window.open('', '', 'width=900,height=700');
    ventana.document.write('<html><head><title>Reporte de Cierre del Día</title>');
    ventana.document.write('<style>@media print { body { -webkit-print-color-adjust: exact; } }</style>');
    ventana.document.write('</head><body>');
    ventana.document.write(contenido);
    ventana.document.write('</body></html>');
    ventana.document.close();
    ventana.focus();
    setTimeout(() => {
        ventana.print();
        ventana.close();
    }, 400);
}
</script>
@endsection