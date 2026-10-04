@extends('adminlte::page')

@section('content_header')
    <h1><b>Listado de pedidos</b></h1>
    <hr>
@stop

@section('content')


{{-- MAPA PEDIDOS NUEVOS --}}
<div class="card mb-4">

    <div class="card-header d-flex justify-content-between align-items-center">

        <h3 class="card-title mb-0">Mapa de pedidos</h3>

        <button id="btnToggleMapa"
                class="btn btn-sm btn-primary"
                onclick="toggleMapa()">
            Ocultar mapa
        </button>

    </div>


    <div class="card-body">

        <div id="contenedorMapaPedidos">

            <div id="mapaPedidosAsignados"
                style="width:100%; height:400px; border-radius:8px;">
            </div>

        </div>

    </div>

</div>


{{-- ===== PEDIDOS NUEVOS ===== --}}
<div id="contenedor-pedidos-nuevos">

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Pedidos nuevos</h3>
        </div>

        <div class="card-body">
            @php
                $pedidosNuevos = $pedidos->where('estado', 'Asignado')
                                          ->where('motoquero_id', $motoquero->id)
                                          ->sortBy('orden')
                                          ->values();
            @endphp

            @forelse($pedidosNuevos as $pedido)
                @php $numeroPedido = $pedido->orden; @endphp

                <div class="pedido-card motoquero-card"
                     data-id="{{ $pedido->id }}"
                     data-cliente="{{ $pedido->cliente->id }}">

                    <div class="pedido-header">
                        <h5><b>{{ $pedido->cliente->nombre }}</b></h5>
                        <div style="font-weight:bold; font-size:1.2em; color:#007bff;">
                            #{{ $numeroPedido }}
                        </div>
                    </div>

                    <p><b>Ubicación GPS:</b>
                        @if($pedido->cliente->ubicacion_gps ?? $pedido->gps)
                            <a href="{{ $pedido->cliente->ubicacion_gps ?? $pedido->gps }}" target="_blank">
                                Ver enlace
                            </a>
                        @else
                            <span class="text-muted">No registrado</span>
                        @endif
                    </p>

                    <p><b>Descripción:</b>
                        {{ $pedido->cliente->direccion ?? $pedido->direccion_entrega }}
                    </p>



                    {{-- 📷 IMAGEN CASA CLIENTE --}}
                    <div class="mt-2 p-2" style="background:#f4f6f9; border-radius:8px;">

                        <b>Imagen de referencia:</b>

                        <div style="margin-top:8px;">

                            @if($pedido->cliente->imagen_casa)
                                <img src="{{ asset('storage/'.$pedido->cliente->imagen_casa) }}"
                                    class="imagen-casa-preview"
                                    data-cliente="{{ $pedido->cliente->id }}"
                                    style="width:120px;height:120px;object-fit:cover;
                                            border-radius:8px;cursor:pointer;
                                            border:1px solid #ddd;">
                            @else
                                <div class="imagen-casa-preview"
                                    data-cliente="{{ $pedido->cliente->id }}"
                                    style="width:120px;height:120px;background:#e9ecef;
                                            display:flex;align-items:center;justify-content:center;
                                            border-radius:8px;cursor:pointer;
                                            border:1px dashed #bbb;">
                                    Sin imagen
                                </div>
                            @endif

                            <form class="form-imagen-casa"
                                data-cliente="{{ $pedido->cliente->id }}"
                                action="{{ route('admin.clientes.imagen', $pedido->cliente->id) }}"
                                enctype="multipart/form-data"
                                style="display:none;">

                                @csrf
                                <input type="file"
                                    name="imagen_casa"
                                    accept="image/*"
                                    capture="environment">
                            </form>

                            <div style="margin-top:6px;">
                                <button type="button"
                                        class="btn btn-sm btn-primary btn-subir-imagen"
                                        data-cliente="{{ $pedido->cliente->id }}">
                                    {{ $pedido->cliente->imagen_casa ? 'Cambiar Imagen' : 'Agregar Imagen' }}
                                </button>
                            </div>

                        </div>
                    </div>
                    {{-- FIN IMAGEN CASA --}}

                    
                                    {{-- 🟢 PRECIO REFERENCIA (dinámico por cliente) --}}
                                        <div class="mt-2 p-2" style="background:#fff8e1; border-radius:6px;"
                                            data-precio-box
                                            data-cliente="{{ $pedido->cliente->id }}">

                                            <b>Precio referencia:</b>
                                            <div style="font-size:0.95rem; margin-top:4px;">
                                                <div>
                                                    Agua normal (ID 1):
                                                    <span class="precio-id-1 text-muted">Cargando...</span>
                                                </div>
                                                <div>
                                                    Agua alcalina (ID 2):
                                                    <span class="precio-id-2 text-muted">Cargando...</span>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- 🟢 FIN PRECIO REFERENCIA --}}

                    {{-- 🔵 ÚLTIMA COMPRA --}}
                    @php
                        $ultimaCompra = $ultimasCompras[$pedido->cliente->id] ?? null;
                    @endphp

                    @if($ultimaCompra && $ultimaCompra->detalles->count() > 0)
                        <div class="mt-2 p-2"
                             style="background:#eef7ff; border-radius:6px;">
                            <b>Última compra:</b>
                            <ul style="margin-left:12px; margin-top:5px; margin-bottom:0;">
                                @foreach($ultimaCompra->detalles as $detalle)
                                    <li style="font-size:0.95rem;">
                                        {{ $detalle->producto }}:
                                        <strong>{{ $detalle->cantidad }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="mb-0">
                            <b>Última compra:</b>
                            <span class="text-muted">Sin historial</span>
                        </p>
                    @endif

                    

                    <div class="pedido-acciones">
                        <form action="{{ url('/admin/pedidos/motoquero/'.$motoquero->id.'/tomar_pedido') }}"
                              method="post"
                              class="form-iniciar-navegacion"
                              data-pedido-id="{{ $pedido->id }}">
                            @csrf
                            <input type="hidden" name="pedido_id" value="{{ $pedido->id }}">
                            <input type="hidden" name="cliente" value="{{ $pedido->cliente->nombre }}">
                            <input type="hidden" name="celular" value="{{ $pedido->cliente->celular_real }}">
                            <input type="hidden" name="motoquero_id" value="{{ $pedido->motoquero_id }}">


                            <button type="submit"
                                    class="btn btn-info btn-sm btn-navegar"
                                    data-id="{{ $pedido->id }}"
                                    data-cliente="{{ $pedido->cliente->nombre }}"
                                    data-orden="{{ $pedido->orden }}"
                                    data-lat="{{ $pedido->cliente->latitud ?? '-'}}"
                                    data-lng="{{ $pedido->cliente->longitud ?? '-'}}">
                                <i class="fas fa-motorcycle"></i> Iniciar Navegación
                            </button>

                        </form>

                        <form id="formRechazar{{ $pedido->id }}"
                            action="{{ url('/admin/pedidos/motoquero/'.$motoquero->id.'/rechazar_pedido') }}"
                            method="post">

                            @csrf

                            <input type="hidden" name="pedido_id" value="{{ $pedido->id }}">

                            <button type="button"
                                    class="btn btn-danger btn-sm btn-rechazar"
                                    data-form="formRechazar{{ $pedido->id }}">

                                <i class="fas fa-times"></i> Rechazar pedido

                            </button>

                        </form>

                    </div>

                </div>
            @empty
                <p class="text-center">No hay pedidos nuevos.</p>
            @endforelse
        </div>
    </div>

</div>

{{-- ===== PEDIDOS EN CAMINO ===== --}}
<div class="card card-warning mt-4">
    <div class="card-header">
        <h3 class="card-title">Pedidos en camino</h3>
    </div>
    <div class="card-body">
        @php
            $pedidosCaminoOrdenados = $pedidos_en_camino->sortBy('orden');
        @endphp

        @forelse($pedidosCaminoOrdenados as $pedido)
            <div class="pedido-card"  id="pedido-{{ $pedido->id }}"  data-id="{{ $pedido->id }}" data-cliente="{{ $pedido->cliente->id }}">
                <div class="pedido-header">
                    <h5><b>{{ $pedido->cliente->nombre }}</b></h5>

                    <div class="d-flex align-items-center">
                        @if($pedido->orden)
                            <span class="mr-2" style="font-weight:bold; font-size:1.1em; color:#fd7e14;">#{{ $pedido->orden }}</span>
                        @endif

                        @if($pedido->emergencia)
                            <span style="color:red;font-size:20px;">🚨</span>
                        @endif

                        <span class="badge badge-warning">{{ $pedido->estado }}</span>

                    </div>

                </div>

                <p><b>Ubicación GPS:</b>
                    @if($pedido->cliente->ubicacion_gps ?? $pedido->gps)
                        <a href="{{ $pedido->cliente->ubicacion_gps ?? $pedido->gps }}" target="_blank">Ver enlace</a>
                    @else
                        <span class="text-muted">No registrado</span>
                    @endif
                </p>

                <p><b>Descripción:</b> {{ $pedido->cliente->direccion ?? $pedido->direccion_entrega }}</p>
                

                    {{-- 📷 IMAGEN CASA CLIENTE --}}
                    <div class="mt-2 p-2" style="background:#f4f6f9; border-radius:8px;">

                        <b>Imagen de referencia:</b>

                        <div style="margin-top:8px;">

                            @if($pedido->cliente->imagen_casa)
                                <img src="{{ asset('storage/'.$pedido->cliente->imagen_casa) }}"
                                    class="imagen-casa-preview"
                                    data-cliente="{{ $pedido->cliente->id }}"
                                    style="width:120px;height:120px;object-fit:cover;
                                            border-radius:8px;cursor:pointer;
                                            border:1px solid #ddd;">
                            @else
                                <div class="imagen-casa-preview"
                                    data-cliente="{{ $pedido->cliente->id }}"
                                    style="width:120px;height:120px;background:#e9ecef;
                                            display:flex;align-items:center;justify-content:center;
                                            border-radius:8px;cursor:pointer;
                                            border:1px dashed #bbb;">
                                    Sin imagen
                                </div>
                            @endif

                            <form class="form-imagen-casa"
                                data-cliente="{{ $pedido->cliente->id }}"
                                action="{{ route('admin.clientes.imagen', $pedido->cliente->id) }}"
                                enctype="multipart/form-data"
                                style="display:none;">

                                @csrf
                                <input type="file"
                                    name="imagen_casa"
                                    accept="image/*"
                                    capture="environment">
                            </form>

                            <div style="margin-top:6px;">
                                <button type="button"
                                        class="btn btn-sm btn-primary btn-subir-imagen"
                                        data-cliente="{{ $pedido->cliente->id }}">
                                    {{ $pedido->cliente->imagen_casa ? 'Cambiar Imagen' : 'Agregar Imagen' }}
                                </button>
                            </div>

                        </div>
                    </div>
                    {{-- FIN IMAGEN CASA --}}



                {{-- 🟢 PRECIO REFERENCIA (dinámico por cliente) --}}
                <div class="mt-2 p-2" style="background:#fff8e1; border-radius:6px;"
                    data-precio-box
                    data-cliente="{{ $pedido->cliente->id }}">

                    <b>Precio referencia:</b>
                    <div style="font-size:0.95rem; margin-top:4px;">
                        <div>
                            Agua normal (ID 1):
                            <span class="precio-id-1 text-muted">Cargando...</span>
                        </div>
                        <div>
                            Agua alcalina (ID 2):
                            <span class="precio-id-2 text-muted">Cargando...</span>
                        </div>
                    </div>
                </div>
                {{-- 🟢 FIN PRECIO REFERENCIA --}}


                {{-- 🔵 ÚLTIMA COMPRA (solo producto + cantidad) usando $ultimasCompras precargado --}}
                @php
                    $ultimaCompra = $ultimasCompras[$pedido->cliente->id] ?? null;
                @endphp

                @if($ultimaCompra && $ultimaCompra->detalles->count() > 0)
                    <div class="mt-2 p-2" style="background: #eef7ff; border-radius: 6px;">
                        <b>Última compra:</b>
                        <ul style="margin-left: 12px; margin-top: 5px; margin-bottom:0;">
                            @foreach($ultimaCompra->detalles as $detalle)
                                <li style="font-size:0.95rem; margin-bottom:2px;">
                                    {{ $detalle->producto }}:
                                    <strong>{{ $detalle->cantidad }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="mb-0"><b>Última compra:</b> <span class="text-muted">Sin historial</span></p>
                @endif
                {{-- 🔵 FIN BLOQUE ÚLTIMA COMPRA --}}


                @php
                $celularCliente = preg_replace('/\D/', '', $pedido->cliente->celular_real ?? $pedido->cliente->celular ?? '');
                if (strlen($celularCliente) == 8) {
                    $celularCliente = '591' . $celularCliente;
                }
                $distribuidorNombre = $motoquero->nombres ?? auth()->user()->name ?? '';
                $mensajeWhatsAppCliente = "👋 Hola, soy el Distribuidor " . $distribuidorNombre . " de Agua La Colina.\n\n"
                                        . "🚚 Ya llegué a su ubicación para entregarle su pedido. Por favor, acérquese para recibirlo.\n\n"
                                        . "¡Gracias! 😊";
                $urlWhatsApp = "https://wa.me/" . $celularCliente . "?text=" . urlencode($mensajeWhatsAppCliente);

                $msgLlegué = "Llegué a la ubicación: " . $pedido->cliente->nombre . ",   ". "+"  . $pedido->cliente->celular_real . ",  ". "https://wa.me/" . $pedido->cliente->celular_real . "?text=Hola,%20el%20distribuidor%20LLEGÓ%20a%20su%20ubicación.%20Por%20favor%20acérquese%20para%20recibir%20el%20pedido." ;                           
                $msgLlegué = urlencode($msgLlegué);
                @endphp

                    <a href="{{ $urlWhatsApp }}" target="_blank" onclick="marcarPedidoEntregando({{ $pedido->id }})" class="btn btn-success btn-sm" title="Enviar WhatsApp de llegada al cliente">
                        <i class="fab fa-whatsapp"></i> Llamar
                    </a>

                    <a href="https://wa.me/59163524474?text={{ $msgLlegué }}" onclick="marcarPedidoEntregando({{ $pedido->id }})" target="_blank" class="btn btn-info btn-sm">
                        <i class="fab fa-whatsapp"></i> Chat Central
                    </a>
                
                    <button class="btn btn-warning btn-sm"
                        onclick="marcarPedidoEntregando({{ $pedido->id }}); solicitarLlamada({{ $pedido->cliente->id }}, '{{ $pedido->cliente->nombre }}', '{{ $pedido->cliente->celular_real }}', '{{ auth()->user()->name }}', '{{ $pedido->motoquero_id }}')">
                        <i class="fas fa-phone"></i> Hacer Llamar
                    </button>

                <div class="pedido-acciones">
                    <form id="formRechazar{{ $pedido->id }}"
                        action="{{ url('/admin/pedidos/motoquero/'.$motoquero->id.'/rechazar_pedido') }}"
                        method="post">

                        @csrf

                        <input type="hidden" name="pedido_id" value="{{ $pedido->id }}">

                        <button type="button"
                                class="btn btn-danger btn-sm btn-rechazar"
                                data-form="formRechazar{{ $pedido->id }}">

                            <i class="fas fa-times"></i> Rechazar pedido

                        </button>

                    </form>

                    <button class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#modalVenta" data-pedido="{{ $pedido->id }}" data-cliente="{{ $pedido->cliente->id }}">
                        <i class="fas fa-check"></i> Finalizar entrega
                    </button>
                </div>
            </div>
        @empty
            <p class="text-center">No hay pedidos en camino.</p>
        @endforelse
    </div>
</div>

{{-- ===== PEDIDOS ENTREGADOS ===== --}}
<div class="card card-success mt-4">
    <div class="card-header">
        <h3 class="card-title">Pedidos entregados</h3>
    </div>
    <div class="card-body">

        <div class="mb-3">
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="fecha">Filtrar por fecha:</label>
                <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $userFiltered ? $fecha : '' }}">
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            </form>
            @if(!$userFiltered)
                <small class="text-muted">
                    Mostrando pedidos entregados de hoy ({{ \Carbon\Carbon::today()->format('d/m/Y') }})
                </small>
            @endif
        </div>


        
        <div style="display:flex; justify-content:center; margin-bottom:30px;">
            <button 
                type="button"
                data-toggle="modal"
                data-target="#reporteModal"
                class="btn btn-success shadow"
                style="padding:12px 28px; font-weight:700; font-size:16px; border-radius:10px;">
                <i class="fas fa-flag-checkered mr-2"></i> Finalizar Día / Reporte de Ventas
            </button>
        </div>


        <div class="scroll-entregados">
            @forelse($pedidos_entregados as $pedido)
                <div class="pedido-card" data-cliente="{{ $pedido->cliente->id }}">
                    <div class="pedido-header">
                        <h5><b>{{ $pedido->cliente->nombre }}</b></h5>

                        <div>

                            @if($pedido->emergencia)
                                <span style="color:red;font-size:20px;">🚨</span>
                            @endif

                            <span class="badge badge-success">{{ $pedido->estado }}</span>

                        </div>

                        
                    </div>

                    <p><b>Ubicación GPS:</b>
                        @if($pedido->cliente->ubicacion_gps ?? $pedido->gps)
                            <a href="{{ $pedido->cliente->ubicacion_gps ?? $pedido->gps }}" target="_blank">Ver enlace</a>
                        @else
                            <span class="text-muted">No registrado</span>
                        @endif
                    </p>

                    <p><b>Descripción:</b> {{ $pedido->cliente->direccion ?? $pedido->direccion_entrega }}</p>
                    

                    </p>

                    @if($pedido->detalles->count() > 0)
                        <table class="table table-sm table-bordered mt-2 tabla-entregados">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio U. (Bs)</th>
                                    <th>Total (Bs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pedido->detalles as $detalle)
                                    
                                    <tr>
                                        <td>{{ $detalle->producto }}</td>
                                        <td>{{ $detalle->cantidad }}</td>
                                        <td>{{ number_format($detalle->precio_unitario, 2) }}</td>
                                        <td>{{ number_format($detalle->precio_unitario * $detalle->cantidad, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="text-end"><b>Total pedido:</b> Bs {{ number_format($pedido->total_precio, 2) }}</p>
                        <p><b>Método de pago:</b> {{ $pedido->metodo_pago ?? 'No definido' }}</p>



                        @if(strtolower($pedido->metodo_pago) === 'qr')

                            @if(!$pedido->qr_pago_estado)

                                <button 
                                    class="btn btn-success btn-sm mt-1 btnPagadoDistribuidor"
                                    data-id="{{ $pedido->id }}">
                                    ✔ Marcar como pagado
                                </button>

                            @elseif($pedido->qr_pago_estado === 'distribuidor')

                                <span class="badge badge-info mt-1">
                                    QR pagado al distribuidor
                                </span>

                            @elseif($pedido->qr_pago_estado === 'central')

                                <span class="badge badge-success mt-1">
                                    QR pagado a la central
                                </span>

                            @endif

                        @endif




                    @endif
                </div>
            @empty
                <p class="text-center">No hay pedidos entregados para esta fecha.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- MODAL FINALIZAR ENTREGA --}}
<div class="modal fade" id="modalVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ url('/admin/pedidos/motoquero/'.$motoquero->id.'/finalizar_pedido') }}">
                @csrf
                <input type="hidden" name="pedido_id" id="modal_pedido_id">
                <input type="hidden" id="modal_cliente_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Finalizar entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    {{-- Tabla de productos --}}
                    <table class="table table-sm text-center tabla-finalizar" id="tablaProductos">
                        <thead class="thead-light">
                            <tr>
                                <th>Producto</th>
                                <th>Precio ref.</th>
                                <th>Cantidad</th>
                                <th>Total (Bs)</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                       
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total general (Bs):</th>
                                <th><input type="number" id="totalGeneral" class="form-control text-center" value="0.00" readonly></th>
                                <th></th>
                            </tr>
                        </tfoot>

                    </table>

                    <div class="text-end mb-3">
                        <button type="button" class="btn btn-info btn-sm" id="agregarFila">
                            <i class="fas fa-plus"></i> Agregar producto
                        </button>
                    </div>

                    {{-- Método de pago --}}
                    <div class="mb-3">
                        <label><b>Método de pago:</b></label>
                        <select name="metodo_pago" id="metodo_pago" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="QR">QR</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success" type="submit">Finalizar</button>
                </div>
            </form>
        </div>
    </div>

</div>


{{-- MODAL PEDIDO DE EMERGENCIA, CONTROL --}}
<div id="modalEmergencia" class="modal">
  <div class="modal-content">
    <h3>🚨 PEDIDO DE EMERGENCIA</h3>
    <p>Cliente: <strong id="emgCliente"></strong></p>

    <a id="btnNavegarEmergencia"
       class="btn btn-danger btn-lg">
       INICIAR NAVEGACIÓN DE EMERGENCIA
    </a>
  </div>
</div>



<audio id="sound-ya-sale" src="{{ asset('sounds/ya_sale.mp3') }}"></audio>
<audio id="sound-no-contesta" src="{{ asset('sounds/no_contesta.mp3') }}"></audio>


<div class="modal fade"
     id="reporteModal"
     tabindex="-1"
     aria-hidden="true">

<div class="modal-dialog modal-xl modal-dialog-scrollable">

<div class="modal-content">

<div class="modal-header bg-success text-white">

<h5 class="modal-title font-weight-bold">
    <i class="fas fa-flag-checkered mr-2"></i> Finalizar Día / Reporte de Ventas y Liquidación
</h5>

<button type="button"
        class="close text-white"
        data-dismiss="modal"
        aria-label="Cerrar">
    <span aria-hidden="true">&times;</span>
</button>

</div>

<div class="modal-body">

@php
$fechaCierre = $userFiltered ? $fecha : now()->toDateString();
$pedidosCierre = $pedidos_entregados;

$vendidosRegular = 0;
$vendidosAlcalina = 0;
$vendidosEnteroRegular = 0;
$vendidosEnteroAlcalina = 0;
$vendidosDispensers = 0;

foreach ($pedidosCierre as $p) {
    foreach ($p->detalles as $det) {
        $nom = strtolower(trim($det->producto));
        if ($nom === 'agua regular') {
            $vendidosRegular += (int)$det->cantidad;
        } elseif ($nom === 'agua alcalina') {
            $vendidosAlcalina += (int)$det->cantidad;
        } elseif (str_contains($nom, 'regular') && (str_contains($nom, 'entero') || str_contains($nom, 'botell'))) {
            $vendidosEnteroRegular += (int)$det->cantidad;
        } elseif (str_contains($nom, 'alcalina') && (str_contains($nom, 'entero') || str_contains($nom, 'botell'))) {
            $vendidosEnteroAlcalina += (int)$det->cantidad;
        } elseif (str_contains($nom, 'dispensador') || str_contains($nom, 'bomba') || str_contains($nom, 'bombita')) {
            $vendidosDispensers += (int)$det->cantidad;
        }
    }
}

$ingresoEfectivo = $pedidosCierre->where('metodo_pago','Efectivo')->sum('total_precio');
$ingresoQR = $pedidosCierre->where('metodo_pago','QR')->sum('total_precio');
$ingresoTotal = $pedidosCierre->sum('total_precio');
$telefonoEmpresa = $configuracion->telefono ?? '59163524474';
$nombreEmpresa = $configuracion->nombre ?? 'La Colina';
@endphp

{{-- ==============================================
    📦 1. RESUMEN DE PRODUCTOS VENDIDOS
============================================== --}}
<div class="card bg-light border-primary mb-3">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-boxes mr-1"></i> Total Vendido por Producto</h6>
        <span class="badge badge-light text-dark font-weight-bold">{{ $pedidosCierre->count() }} entregas</span>
    </div>
    <div class="card-body py-2">
        <div class="row text-center">
            <div class="col-md col-6 border-right py-2">
                <span class="text-muted d-block small font-weight-bold">BOTELLONES NORMALES</span>
                <span class="h4 font-weight-bold text-primary">{{ $vendidosRegular }}</span>
                <small class="d-block text-muted">Agua Regular</small>
            </div>
            <div class="col-md col-6 border-right py-2">
                <span class="text-muted d-block small font-weight-bold">ALCALINAS</span>
                <span class="h4 font-weight-bold text-info">{{ $vendidosAlcalina }}</span>
                <small class="d-block text-muted">Agua Alcalina</small>
            </div>
            <div class="col-md col-6 border-right py-2">
                <span class="text-muted d-block small font-weight-bold">ENTEROS NORMALES</span>
                <span class="h4 font-weight-bold text-success">{{ $vendidosEnteroRegular }}</span>
                <small class="d-block text-muted">Botellón + Regular</small>
            </div>
            <div class="col-md col-6 border-right py-2">
                <span class="text-muted d-block small font-weight-bold">ENTEROS ALCALINAS</span>
                <span class="h4 font-weight-bold text-warning">{{ $vendidosEnteroAlcalina }}</span>
                <small class="d-block text-muted">Botellón + Alcalina</small>
            </div>
            <div class="col-md col-6 border-right py-2">
                <span class="text-muted d-block small font-weight-bold">DISPENSERS</span>
                <span class="h4 font-weight-bold" style="color: #6f42c1;">{{ $vendidosDispensers }}</span>
                <small class="d-block text-muted">Mesa / Bombitas</small>
            </div>
            <div class="col-md col-6 py-2">
                <span class="text-muted d-block small font-weight-bold">TOTAL BOTELLONES</span>
                <span class="h4 font-weight-bold text-dark">{{ $vendidosRegular + $vendidosAlcalina + $vendidosEnteroRegular + $vendidosEnteroAlcalina }}</span>
                <small class="d-block text-muted">Unidades</small>
            </div>
        </div>
    </div>
</div>

{{-- ==============================================
    💰 2. RESUMEN DE INGRESOS
============================================== --}}
<div class="row mb-3">
    <div class="col-md-4 mb-2">
        <div class="card border-success h-100 shadow-sm">
            <div class="card-body p-3 text-center">
                <span class="text-muted d-block small font-weight-bold">TOTAL EFECTIVO RECAUDADO</span>
                <span class="h3 font-weight-bold text-success">Bs. {{ number_format($ingresoEfectivo, 2) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card border-primary h-100 shadow-sm">
            <div class="card-body p-3 text-center">
                <span class="text-muted d-block small font-weight-bold">TOTAL EN QR / TRANSFERENCIA</span>
                <span class="h3 font-weight-bold text-primary">Bs. {{ number_format($ingresoQR, 2) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card border-dark h-100 shadow-sm">
            <div class="card-body p-3 text-center">
                <span class="text-muted d-block small font-weight-bold">TOTAL INGRESO BRUTO</span>
                <span class="h3 font-weight-bold text-dark">Bs. {{ number_format($ingresoTotal, 2) }}</span>
            </div>
        </div>
    </div>
</div>

{{-- ==============================================
    ⛽ 3. FORMULARIO DE GASTOS Y RENDICIÓN
============================================== --}}
<form method="POST" action="{{ route('admin.contabilidad.confirmar_venta.store') }}" id="form-cierre-modal">
    @csrf
    <input type="hidden" name="fecha" value="{{ $fechaCierre }}">
    <input type="hidden" name="distribuidor_id" value="{{ $motoquero->id }}">
    <input type="hidden" name="ingreso_bruto" value="{{ $ingresoTotal }}">
    <input type="hidden" name="ingreso_efectivo" value="{{ $ingresoEfectivo }}">
    <input type="hidden" name="ingreso_qr" value="{{ $ingresoQR }}">

    <div class="card border-warning mb-3">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center py-2">
            <h6 class="mb-0 font-weight-bold"><i class="fas fa-receipt text-danger mr-1"></i> Gastos del Día (Combustible, Otros)</h6>
            <div>
                <button type="button" class="btn btn-sm btn-dark font-weight-bold" id="modal-agregar-combustible">
                    <i class="fas fa-gas-pump text-warning"></i> + Agregar Combustible
                </button>
                <button type="button" class="btn btn-sm btn-secondary font-weight-bold ml-2" id="modal-agregar-otro">
                    <i class="fas fa-plus-circle text-info"></i> + Agregar Otro Gasto
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <table class="table table-bordered table-sm mb-2" id="modal-tabla-gastos">
                <thead class="table-light">
                    <tr>
                        <th>Concepto / Detalle del Gasto</th>
                        <th width="200">Monto (Bs.)</th>
                        <th width="50" class="text-center">✕</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($cierreExistente) && $cierreExistente && $cierreExistente->gastos && $cierreExistente->gastos->count() > 0)
                        @foreach($cierreExistente->gastos as $idx => $g)
                            <tr>
                                <td>
                                    <input type="text"
                                           name="gastos[{{ $idx }}][concepto]"
                                           class="form-control form-control-sm modal-concepto-gasto"
                                           value="{{ $g->concepto }}"
                                           placeholder="Ej: Combustible, Pinchazo..." required>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">Bs.</span></div>
                                        <input type="number"
                                               step="0.01"
                                               name="gastos[{{ $idx }}][monto]"
                                               class="form-control form-control-sm modal-monto-gasto"
                                               value="{{ $g->monto }}" required>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-danger modal-eliminar-fila">✕</button>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>
                                <input type="text"
                                       name="gastos[0][concepto]"
                                       class="form-control form-control-sm modal-concepto-gasto"
                                       value="Combustible"
                                       placeholder="Ej: Combustible" required>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text">Bs.</span></div>
                                    <input type="number"
                                           step="0.01"
                                           name="gastos[0][monto]"
                                           class="form-control form-control-sm modal-monto-gasto"
                                           value="0" required>
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-danger modal-eliminar-fila">✕</button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            {{-- TOTALES LIQUIDACIÓN --}}
            <div class="row align-items-center bg-light border rounded p-2 mx-0 mt-3">
                <div class="col-md-6">
                    <p class="mb-1 text-danger font-weight-bold">
                        Total Gastos: - Bs. <span id="modal-total-gastos">0.00</span>
                    </p>
                    <small class="text-muted">Se deducen únicamente del monto en efectivo recaudado.</small>
                </div>
                <div class="col-md-6 text-right">
                    <span class="text-muted d-block small font-weight-bold">TOTAL A ENTREGAR EN EFECTIVO:</span>
                    <span class="h4 font-weight-bold text-success mb-0">Bs. <span id="modal-efectivo-entregar">{{ number_format($ingresoEfectivo, 2) }}</span></span>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
            <div>
                <button type="button" class="btn btn-info btn-sm font-weight-bold mr-2" onclick="imprimirReporteModal()">
                    <i class="fas fa-print"></i> Descargar / Imprimir Reporte
                </button>
                <a href="#" id="modal-btn-whatsapp-empresa" target="_blank" class="btn btn-success btn-sm font-weight-bold">
                    <i class="fab fa-whatsapp"></i> Enviar a WhatsApp Empresa
                </a>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Guardar Cierre de Día
                </button>
            </div>
        </div>
    </div>
</form>

{{-- ==============================================
    📋 4. DETALLE DE PEDIDOS ENTREGADOS
============================================== --}}
<details class="mt-3">
    <summary class="font-weight-bold text-primary" style="cursor: pointer;">
        Ver listado individual de pedidos entregados ({{ $pedidosCierre->count() }})
    </summary>
    <div class="table-responsive mt-2">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Método</th>
                    <th>Hora</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pedidosCierre as $pedido)
                    <tr>
                        <td>#{{ $pedido->orden > 0 ? $pedido->orden : $loop->iteration }}</td>
                        <td>{{ $pedido->cliente->nombre ?? 'Sin cliente' }}</td>
                        <td>Bs. {{ number_format($pedido->total_precio,2) }}</td>
                        <td>
                            @if($pedido->metodo_pago == 'QR')
                                <span class="badge badge-success">QR</span>
                            @else
                                <span class="badge badge-primary">Efectivo</span>
                            @endif
                        </td>
                        <td>{{ $pedido->updated_at->format('H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">No hay pedidos entregados en esta fecha</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</details>

{{-- ÁREA OCULTA PARA IMPRESIÓN DEL MODAL --}}
<div id="modal-area-impresion" class="d-none">
    <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 750px; margin: 0 auto; color: #333;">
        <div style="text-align: center; border-bottom: 2px solid #0056b3; padding-bottom: 12px; margin-bottom: 15px;">
            <h2 style="margin: 0; color: #0056b3;">{{ $nombreEmpresa }}</h2>
            <p style="margin: 3px 0; font-size: 14px; color: #666;">Reporte de Liquidación de Entrega y Cierre Diario</p>
            <p style="margin: 2px 0; font-size: 13px;">Fecha: <b>{{ \Carbon\Carbon::parse($fechaCierre)->format('d/m/Y') }}</b> | Teléfono: {{ $telefonoEmpresa }}</p>
        </div>

        <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
            <p style="margin: 3px 0;"><b>Distribuidor:</b> {{ $motoquero->nombres }} {{ $motoquero->apellidos }}</p>
            <p style="margin: 3px 0;"><b>Placa / Vehículo:</b> {{ $motoquero->placa ?? 'N/A' }} | <b>Total Entregas Realizadas:</b> {{ $pedidosCierre->count() }}</p>
        </div>

        <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 4px; color: #0056b3;">1. Productos Vendidos</h4>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 14px;">
            <thead>
                <tr style="background: #e9ecef;">
                    <th style="border: 1px solid #ccc; padding: 6px; text-align: left;">Producto / Categoría</th>
                    <th style="border: 1px solid #ccc; padding: 6px; text-align: center;">Cantidad</th>
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
        <div id="modal-print-gastos-lista" style="margin-bottom: 15px;"></div>

        <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 4px; color: #0056b3;">3. Resumen Financiero</h4>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 14px;">
            <tr><td style="border: 1px solid #ccc; padding: 6px;">Total Efectivo Recaudado:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><b>Bs. {{ number_format($ingresoEfectivo, 2) }}</b></td></tr>
            <tr><td style="border: 1px solid #ccc; padding: 6px;">Total en QR / Transferencia:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><b>Bs. {{ number_format($ingresoQR, 2) }}</b></td></tr>
            <tr><td style="border: 1px solid #ccc; padding: 6px;">Ingreso Bruto Total:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><b>Bs. {{ number_format($ingresoTotal, 2) }}</b></td></tr>
            <tr><td style="border: 1px solid #ccc; padding: 6px; color: #c00;">Total Gastos Deducidos:</td><td style="border: 1px solid #ccc; padding: 6px; text-align: right; color: #c00;"><b>- Bs. <span id="modal-print-total-gastos">0.00</span></b></td></tr>
            <tr style="background: #d4edda; font-size: 16px; font-weight: bold; color: #155724;">
                <td style="border: 1px solid #28a745; padding: 8px;">TOTAL A ENTREGAR EN EFECTIVO:</td>
                <td style="border: 1px solid #28a745; padding: 8px; text-align: right;">Bs. <span id="modal-print-efectivo-entregar">0.00</span></td>
            </tr>
        </table>

        <div style="display: flex; justify-content: space-between; margin-top: 50px; text-align: center;">
            <div style="width: 40%; border-top: 1px solid #333; padding-top: 6px;">
                <p style="margin: 0; font-size: 13px;">Firma Distribuidor</p>
            </div>
            <div style="width: 40%; border-top: 1px solid #333; padding-top: 6px;">
                <p style="margin: 0; font-size: 13px;">Firma Recepción Administración</p>
            </div>
        </div>
    </div>
</div>

</div>

</div>

</div>

</div>

{{-- SCRIPT PARA EL MODAL DE FINALIZAR DÍA --}}
<script>
let modalIndexGasto = {{ (isset($cierreExistente) && $cierreExistente && $cierreExistente->gastos) ? $cierreExistente->gastos->count() : 1 }};

const modalIngresoEfectivo = {{ $ingresoEfectivo ?? 0 }};
const modalIngresoQR       = {{ $ingresoQR ?? 0 }};
const modalIngresoTotal    = {{ $ingresoTotal ?? 0 }};

const modalVendidosRegular        = {{ $vendidosRegular ?? 0 }};
const modalVendidosAlcalina       = {{ $vendidosAlcalina ?? 0 }};
const modalVendidosEnteroRegular  = {{ $vendidosEnteroRegular ?? 0 }};
const modalVendidosEnteroAlcalina = {{ $vendidosEnteroAlcalina ?? 0 }};
const modalVendidosDispensers     = {{ $vendidosDispensers ?? 0 }};

const modalFechaFmt       = "{{ \Carbon\Carbon::parse($fechaCierre)->format('d/m/Y') }}";
const modalMotoqueroNom   = "{{ $motoquero->nombres }} {{ $motoquero->apellidos }}";
const modalTelefonoEmpresa = "{{ $telefonoEmpresa }}";
const modalNombreEmpresa  = "{{ $nombreEmpresa }}";

// Botón Agregar Combustible
document.getElementById('modal-agregar-combustible')?.addEventListener('click', () => {
    modalAgregarFilaGasto('Combustible');
});

// Botón Agregar Otro Gasto
document.getElementById('modal-agregar-otro')?.addEventListener('click', () => {
    modalAgregarFilaGasto('');
});

function modalAgregarFilaGasto(conceptoDefecto = '') {
    const tbody = document.querySelector('#modal-tabla-gastos tbody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="text"
                   name="gastos[${modalIndexGasto}][concepto]"
                   class="form-control form-control-sm modal-concepto-gasto"
                   value="${conceptoDefecto}"
                   placeholder="Ej: Combustible, Pinchazo, Peaje..." required>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <div class="input-group-prepend"><span class="input-group-text">Bs.</span></div>
                <input type="number"
                       step="0.01"
                       name="gastos[${modalIndexGasto}][monto]"
                       class="form-control form-control-sm modal-monto-gasto"
                       value="0" required>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-xs btn-danger modal-eliminar-fila">✕</button>
        </td>
    `;
    tbody.appendChild(tr);
    modalIndexGasto++;
    recalcularModalTotales();
}

// Eliminar fila
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-eliminar-fila')) {
        e.target.closest('tr').remove();
        recalcularModalTotales();
    }
});

// Cambios en inputs de gastos
document.addEventListener('input', e => {
    if (e.target.classList.contains('modal-monto-gasto') || e.target.classList.contains('modal-concepto-gasto')) {
        recalcularModalTotales();
    }
});

function recalcularModalTotales() {
    let totGastos = 0;
    let items = [];

    document.querySelectorAll('#modal-tabla-gastos tbody tr').forEach(row => {
        const conc = row.querySelector('.modal-concepto-gasto')?.value.trim() || 'Gasto';
        const mont = parseFloat(row.querySelector('.modal-monto-gasto')?.value || 0);
        if (mont > 0) {
            totGastos += mont;
            items.push(`• ${conc}: Bs. ${mont.toFixed(2)}`);
        }
    });

    const efectivoAEntregar = Math.max(0, modalIngresoEfectivo - totGastos);

    const elTotGastos = document.getElementById('modal-total-gastos');
    if (elTotGastos) elTotGastos.innerText = totGastos.toFixed(2);

    const elEfecEntregar = document.getElementById('modal-efectivo-entregar');
    if (elEfecEntregar) elEfecEntregar.innerText = efectivoAEntregar.toFixed(2);

    // Para impresión
    const printTotG = document.getElementById('modal-print-total-gastos');
    if (printTotG) printTotG.innerText = totGastos.toFixed(2);

    const printEfecE = document.getElementById('modal-print-efectivo-entregar');
    if (printEfecE) printEfecE.innerText = efectivoAEntregar.toFixed(2);

    const printGList = document.getElementById('modal-print-gastos-lista');
    if (printGList) {
        if (items.length > 0) {
            let h = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
            document.querySelectorAll('#modal-tabla-gastos tbody tr').forEach(row => {
                const conc = row.querySelector('.modal-concepto-gasto')?.value.trim() || 'Gasto';
                const mont = parseFloat(row.querySelector('.modal-monto-gasto')?.value || 0);
                if (mont > 0) {
                    h += `<tr><td style="border: 1px solid #ccc; padding: 4px 6px;">${conc}</td><td style="border: 1px solid #ccc; padding: 4px 6px; text-align: right;">Bs. ${mont.toFixed(2)}</td></tr>`;
                }
            });
            h += '</table>';
            printGList.innerHTML = h;
        } else {
            printGList.innerHTML = '<p style="color: #888; font-style: italic;">Sin gastos registrados</p>';
        }
    }

    // Actualizar WhatsApp URL
    actualizarModalWhatsApp(totGastos, efectivoAEntregar, items);
}

function actualizarModalWhatsApp(totGastos, efectivoAEntregar, items) {
    const btn = document.getElementById('modal-btn-whatsapp-empresa');
    if (!btn) return;

    const textoGastos = items.length > 0 ? items.join('\n') : '• Sin gastos registrados';

    const msg = `📋 *CIERRE DE VENTAS DEL DÍA*\n` +
        `📅 *Fecha:* ${modalFechaFmt}\n` +
        `🛵 *Distribuidor:* ${modalMotoqueroNom}\n` +
        `🏢 *Empresa:* ${modalNombreEmpresa}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `📦 *PRODUCTOS VENDIDOS:*\n` +
        `• Botellones normales: ${modalVendidosRegular}\n` +
        `• Alcalinas: ${modalVendidosAlcalina}\n` +
        `• Enteros normales: ${modalVendidosEnteroRegular}\n` +
        `• Enteros alcalinas: ${modalVendidosEnteroAlcalina}\n` +
        `• Dispensers: ${modalVendidosDispensers}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `💰 *RESUMEN DE INGRESOS:*\n` +
        `💵 Total Efectivo: Bs. ${modalIngresoEfectivo.toFixed(2)}\n` +
        `📱 Total en QR: Bs. ${modalIngresoQR.toFixed(2)}\n` +
        `💎 Total Ingresos: Bs. ${modalIngresoTotal.toFixed(2)}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `⛽ *GASTOS REGISTRADOS:*\n` +
        `${textoGastos}\n` +
        `🔻 Total Gastos: Bs. ${totGastos.toFixed(2)}\n` +
        `━━━━━━━━━━━━━━━━━━━━\n` +
        `💵 *TOTAL A ENTREGAR EN EFECTIVO:*\n` +
        `👉 *Bs. ${efectivoAEntregar.toFixed(2)}*\n` +
        `━━━━━━━━━━━━━━━━━━━━`;

    btn.href = `https://wa.me/${modalTelefonoEmpresa}?text=${encodeURIComponent(msg)}`;
}

// Inicializar cuando se abra el modal
$('#reporteModal').on('shown.bs.modal', function () {
    recalcularModalTotales();
});

document.addEventListener('DOMContentLoaded', () => {
    recalcularModalTotales();
});

function imprimirReporteModal() {
    recalcularModalTotales();
    const contenido = document.getElementById('modal-area-impresion').innerHTML;
    const ventana = window.open('', '', 'width=900,height=700');
    ventana.document.write('<html><head><title>Reporte Cierre del Día</title>');
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


@stop

@section('css')
<style>
.pedido-card { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.pedido-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.pedido-acciones { display: flex; justify-content: flex-start; gap: 10px; margin-top: 10px; }
.scroll-entregados { max-height: 500px; overflow-y: auto; padding-right: 10px; scrollbar-width: thin; scrollbar-color: #28a745 #e9ecef; }
.scroll-entregados::-webkit-scrollbar { width: 8px; }
.scroll-entregados::-webkit-scrollbar-thumb { background-color: #28a745; border-radius: 10px; }
.scroll-entregados::-webkit-scrollbar-track { background: #e9ecef; }
</style>

<style>
.tabla-finalizar { font-size: 13px; table-layout: fixed; width: 100%;}
.tabla-finalizar th,
.tabla-finalizar td {padding: 5px 6px; vertical-align: middle;}
/* Producto */
.tabla-finalizar th:nth-child(1),
.tabla-finalizar td:nth-child(1) {width: 28%;}
/* Precio ref */
.tabla-finalizar th:nth-child(2),
.tabla-finalizar td:nth-child(2) {width: 14%;}
/* Cantidad (más angosta) */
.tabla-finalizar th:nth-child(3),
.tabla-finalizar td:nth-child(3) {width: 12%;}
/* Total (más ancho) */
.tabla-finalizar th:nth-child(4),
.tabla-finalizar td:nth-child(4) {width: 18%;white-space: nowrap;}
/* Acción */
.tabla-finalizar th:nth-child(5),
.tabla-finalizar td:nth-child(5) {width: 12%;}
/* Inputs más pequeños */
.tabla-finalizar input,
.tabla-finalizar select { height: 30px; font-size: 13px; padding: 3px 6px;}
/* Evita que el modal se desborde en móvil */
#modalVenta .modal-body {overflow-x: auto;}
</style>
<style>
/* Contenedor scroll horizontal solo si es necesario */
.scroll-entregados { overflow-x: auto;}
/* Tabla más compacta */
.tabla-entregados { font-size: 13px; table-layout: fixed; width: 100%;}
.tabla-entregados th,
.tabla-entregados td { padding: 4px 6px; vertical-align: middle;}
/* Producto */
.tabla-entregados th:nth-child(1),
.tabla-entregados td:nth-child(1) { width: 35%;}
/* Cantidad (MUCHO más angosta) */
.tabla-entregados th:nth-child(2),
.tabla-entregados td:nth-child(2) { width: 10%; text-align: center;}
/* Precio Unitario */
.tabla-entregados th:nth-child(3),
.tabla-entregados td:nth-child(3) { width: 20%; text-align: right; white-space: nowrap;}
/* Total */
.tabla-entregados th:nth-child(4),
.tabla-entregados td:nth-child(4) {width: 20%;text-align: right;white-space: nowrap;}
/* En móvil reducir aún más */
@media (max-width: 576px) {
.tabla-entregados {font-size: 12px;}
.tabla-entregados th,
.tabla-entregados td {padding: 3px 4px;}}


/* ===== TEXTOS MÁS FUERTES EN PEDIDOS ===== */

.pedido-card p,
.pedido-card li,
.pedido-card span,
.pedido-card b { color: #000 !important; font-weight: 600;}

/* Precio referencia */
[data-precio-box] { font-weight: 600; color: #000;}

/* Última compra */
.pedido-card ul li { font-weight: 600;}

/* descripción */
.pedido-card p { margin-bottom: 6px;}

/* ===== MODAL FINALIZAR ENTREGA ===== */
.tabla-finalizar th,
.tabla-finalizar td { font-weight: 600; color: #000;}
.tabla-finalizar select,
.tabla-finalizar input { font-weight: 600; color: #000;}
.pedido-entregando { border: 3px solid #6f4e37 !important; background-color: #f3e5d8; position: relative;}
.badge-entregando { position: absolute; top: -10px; left: 10px; background: #6f4e37; color: white; padding: 4px 10px; font-size: 11px; border-radius: 4px;}

</style>
@stop

@section('js')


<script async
  src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}">
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabla = document.querySelector('#tablaProductos tbody');
    const totalGeneralInput = document.getElementById('totalGeneral');

    // Función para actualizar total de una fila
    function actualizarFilaTotal(fila) {
        const precio = parseFloat(fila.querySelector('.precio').value) || 0;
        const cantidad = parseFloat(fila.querySelector('.cantidad').value) || 0;
        fila.querySelector('.total').value = (precio * cantidad).toFixed(2);
    }

    // Función para actualizar total general
    function actualizarTotalGeneral() {
        let total = 0;
        document.querySelectorAll('.total').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        totalGeneralInput.value = total.toFixed(2);
    }


    // Actualizar total al cambiar cantidad
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('cantidad')) {
            const fila = e.target.closest('tr');
            actualizarFilaTotal(fila);
            actualizarTotalGeneral();
        }
    });


    document.addEventListener('click', function(e) {
        if (e.target.closest('.eliminar-fila')) {
            e.target.closest('tr').remove();
            actualizarTotalGeneral();
        }
    });


    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('producto-select')) {

            const fila = e.target.closest('tr');
            const productoId = e.target.value;
            const clienteId = document.getElementById('modal_cliente_id').value;

            if (!productoId) return;

            fetch(`/admin/pedidos/precio-cliente/${clienteId}/${productoId}`)
                .then(r => r.json())
                .then(data => {
                    // VISUAL
                    fila.querySelector('.precio-ref').innerText = data.precio + ' Bs';

                    // OCULTO
                    fila.querySelector('.precio').value = data.precio;

                    // recalcular
                    actualizarFilaTotal(fila);
                    actualizarTotalGeneral();
                });
        }
    });


    // Función para crear fila nueva
    function crearFila() {
        const fila = document.createElement('tr');

        let options = '<option value="">Seleccione...</option>';

        @foreach($productos as $prod)
            options += `<option value="{{ $prod->id }}">{{ $prod->nombre }}</option>`;
        @endforeach

        fila.innerHTML = `
            <td>
                <select name="producto_id[]" class="form-control producto-select" required>
                    ${options}
                </select>
            </td>

            <td class="text-center">
                <span class="precio-ref text-muted">—</span>
                <input type="hidden" class="precio" value="0">
            </td>

            <td>
                <input type="number" min="1" name="cantidad[]" 
                    class="form-control cantidad" value="1" required>
            </td>

            <td>
                <input type="number" step="0.01" 
                    class="form-control total" readonly value="0.00">
            </td>

            <td>
                <button type="button" class="btn btn-danger btn-sm eliminar-fila">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tabla.appendChild(fila);
    }


    // Agregar fila
    document.getElementById('agregarFila').addEventListener('click', function() {
        const cliente_id = document.getElementById('modal_cliente_id').value;
        crearFila(cliente_id);
    });

    // Abrir modal
    const modal = document.getElementById('modalVenta');
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const pedidoId = button.getAttribute('data-pedido');
        const clienteId = button.getAttribute('data-cliente'); // Pasar cliente_id al botón
        document.getElementById('modal_pedido_id').value = pedidoId;
        document.getElementById('modal_cliente_id').value = clienteId;

        // Reset tabla
        tabla.querySelectorAll('tr').forEach(tr => tr.remove());
        crearFila(clienteId);

        // Reset método de pago
        document.getElementById('metodo_pago').value = "";
        totalGeneralInput.value = "0.00";
    });
});


</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Indica si el usuario filtró manualmente (desde el servidor)
    const userFiltered = @json($userFiltered ?? false);

    // Elemento fecha
    const fechaInput = document.getElementById('fecha');
    if (!fechaInput) return;

    // Fecha hoy en formato YYYY-MM-DD
    const hoy = new Date().toISOString().slice(0,10);

    // --- Comportamiento 1: si la página fue abierta otro día (última visita diferente)
    // y el usuario NO filtró manualmente, recargamos con la fecha de hoy para que
    // se muestren los entregados de hoy.
    try {
        const storageKey = 'pedidos_entregados_last_visit_date';
        const lastVisit = localStorage.getItem(storageKey);

        if (!userFiltered) {
            // Si no hay lastVisit o es distinto a hoy y el input no es hoy -> recarga
            if ((!lastVisit || lastVisit !== hoy) && fechaInput.value !== hoy) {
                // Guardar hoy para evitar bucles y recargar con ?fecha=hoy
                localStorage.setItem(storageKey, hoy);

                // Construimos la URL actual manteniendo otros query params salvo 'fecha'
                const url = new URL(window.location.href);
                url.searchParams.set('fecha', hoy);
                window.location.href = url.toString();
                return;
            }

            // Guardamos la visita actual
            localStorage.setItem(storageKey, hoy);
        }
    } catch (e) {
        // si storage falla, no pasa nada
        console.warn('localStorage no disponible', e);
    }

    // --- Comportamiento 2: si la app queda abierta y llega la medianoche,
    // recargar automáticamente (solo si el usuario no filtró manualmente)
    if (!userFiltered) {
        const now = new Date();
        const manana = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
        const msUntilMidnight = manana - now;

        // Programamos recarga a la medianoche para actualizar la fecha y la lista
        setTimeout(function() {
            const url = new URL(window.location.href);
            const nuevaHoy = new Date().toISOString().slice(0,10);
            url.searchParams.set('fecha', nuevaHoy);
            // actualiza localStorage antes de recargar para no crear bucle
            try { localStorage.setItem('pedidos_entregados_last_visit_date', nuevaHoy); } catch (_) {}
            window.location.href = url.toString();
        }, msUntilMidnight + 1000); // +1s para asegurarnos que ya es el nuevo día
    }

});
</script>


<script>
/**
 * ENVÍA AVISO AL ADMIN CUANDO SE INICIA NAVEGACIÓN
 */
function enviarAvisoDeNavegacion(pedidoId, cliente, celular, motoqueroId) {
    fetch("/admin/avisos-navegacion/crear", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": "{{ csrf_token() }}",
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            pedido_id: pedidoId,
            cliente: cliente,
            celular: celular,
            motoquero_id: motoqueroId
        })
    }).catch(err => console.error("Error enviando aviso navegación:", err));
}


/**
 * INTERCEPTA SUBMIT DE "INICIAR NAVEGACIÓN"
 * (funciona aunque el HTML se recargue)
 */
document.addEventListener('submit', function (e) {

    const form = e.target;
    const btn = form.querySelector('.btn-navegar');

    // Si no es el formulario de navegación, salir
    if (!btn) return;

    // ========================
    // DATOS
    // ========================
    const pedidoId   = form.querySelector('input[name="pedido_id"]')?.value;
    const cliente    = form.querySelector('input[name="cliente"]')?.value;
    const celular    = form.querySelector('input[name="celular"]')?.value;
    const motoqueroId= form.querySelector('input[name="motoquero_id"]')?.value;

    const lat = btn.dataset.lat || null;
    const lng = btn.dataset.lng || null;

    // ========================
    // AVISO AL ADMIN
    // ========================
    enviarAvisoDeNavegacion(pedidoId, cliente, celular, motoqueroId);





    // ========================
    // GUARDAR COORDENADAS
    // ========================
    if (lat && lng) {
        sessionStorage.setItem('nav_lat', lat);
        sessionStorage.setItem('nav_lng', lng);
    }

    // marcar que debemos hacer scroll cuando vuelva la página
    sessionStorage.setItem('scroll_en_camino', '1');

});



document.addEventListener('DOMContentLoaded', function () {

    const scrollPendiente = sessionStorage.getItem('scroll_en_camino');

    if(scrollPendiente === '1'){

        sessionStorage.removeItem('scroll_en_camino');

        setTimeout(() => {

            const seccion = document.querySelector('.card-warning');

            if (seccion) {
                seccion.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }

        }, 800);

    }

});


/**
 * DETECTAR PLATAFORMA
 */
function getPlatform() {
    const ua = navigator.userAgent || navigator.vendor || window.opera;

    if (/android/i.test(ua)) return "android";
    if (/iPad|iPhone|iPod/.test(ua) && !window.MSStream) return "ios";
    return "desktop";
}


/**
 * ABRIR GOOGLE MAPS DESPUÉS DEL SUBMIT
 */
document.addEventListener('DOMContentLoaded', function () {

    const lat = sessionStorage.getItem('nav_lat');
    const lng = sessionStorage.getItem('nav_lng');

    if (!lat || !lng) return;

    // Limpiar para no repetir
    sessionStorage.removeItem('nav_lat');
    sessionStorage.removeItem('nav_lng');

    let url = '';
    const platform = getPlatform();

    if (platform === 'android') {
        url = `intent://maps.google.com/maps?daddr=${lat},${lng}&travelmode=driving#Intent;scheme=https;package=com.google.android.apps.maps;end`;
    } else if (platform === 'ios') {
        url = `maps://?daddr=${lat},${lng}&dirflg=d`;
    } else {
        url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
    }

    window.open(url, "_blank");
});
</script>

<script>
// REEMPLAZAR tu función solicitarLlamada por esta versión con SweetAlert2
function solicitarLlamada(clienteId, nombre, celular, motoquero, motoqueroID) {

    fetch("{{ route('solicitar.llamada') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify({
            cliente_id: clienteId,
            nombre_cliente: nombre,
            celular_cliente: celular,
            nombre_motoquero: motoquero,
            motoquero_id: motoqueroID

        })
    })
    .then(r => r.json())
    .then(() => {
        // SweetAlert2 Toast
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Solicitud enviada al Administrador ✔',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            background: '#28a745', // color verde como éxito
            color: '#fff',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo enviar la solicitud'
        });
    });
}
</script>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Cargar el audio
const audio = new Audio("/sounds/alertallegada.mp3");
audio.preload = "auto";

// Vibración
function vibrar() {
    if (navigator.vibrate) {
        navigator.vibrate([300, 200, 300]);
    }
}

// Revisar cada 5 segundos si hay llamada atendida
setInterval(() => {
    fetch("{{ route('llamadas.checkMotoquero') }}", {
        headers: {
            'Accept': 'application/json'
        },
        redirect: 'manual'
    })
    .then(res => {
        // ❌ jamás seguir redirects
        if (res.status === 302) return null;
        if (res.status === 204) return null;
        if (res.status === 401) return null;

        return res.json();
    })
    .then(data => {
        if (!data || !data.notificacion) return;

        // 🔊 Sonido
        audio.currentTime = 0;
        audio.play().catch(() => {});

        // 📳 Vibración
        vibrar();

        // 🔔 SweetAlert
        Swal.fire({
            title: "📞 Cliente Avisado",
            html: `
                <p>El cliente <strong>${data.cliente}</strong> ya fue avisado.</p>
                <p>Celular: <strong>${data.celular}</strong></p>
            `,
            icon: "success",
            confirmButtonText: "Entendido",
            confirmButtonColor: "#198754",
            backdrop: true
        });
    })
    .catch(() => {});
}, 5000);
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    setInterval(() => {

        // Si la pestaña no está activa, no recargar
        if (document.hidden) return;

        fetch(window.location.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.text())
        .then(html => {

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const nuevo = doc.querySelector('#contenedor-pedidos-nuevos');
            const actual = document.querySelector('#contenedor-pedidos-nuevos');

            if (nuevo && actual) {

                actual.innerHTML = nuevo.innerHTML;

                // 🔄 actualizar mapa con nuevos pedidos
                actualizarMarcadoresMapa();

            }

        })
        .catch(err => console.error('Error refrescando pedidos nuevos:', err));

    }, 5000); // ⏱ cada 5 segundos

});
</script>


<script>
let pedidoEmergenciaId = null;

/**
 * Mostrar modal de emergencia
 * (lo llama el poll / fetch)
 */
function mostrarModalEmergencia(data) {

    pedidoEmergenciaId = data.pedido_id;

    document.getElementById('emgCliente').innerText = data.cliente;

    const modal = new bootstrap.Modal(
        document.getElementById('modalEmergencia'),
        { backdrop: 'static', keyboard: false }
    );

    modal.show();
}


/**
 * Botón "INICIAR NAVEGACIÓN" del modal
 * 👉 SIMULA el submit real
 */
document.getElementById('btnNavegarEmergencia')
.addEventListener('click', function () {

    if (!pedidoEmergenciaId) {
        alert('Pedido de emergencia no válido');
        return;
    }

    const form = document.querySelector(
        `.form-iniciar-navegacion[data-pedido-id="${pedidoEmergenciaId}"]`
    );

    if (!form) {
        alert('El pedido no está disponible en la lista');
        return;
    }

    // 🔥 TOMAR LAT / LNG DEL BOTÓN REAL
    const btn = form.querySelector('.btn-navegar');
    const lat = btn?.dataset.lat;
    const lng = btn?.dataset.lng;

    if (lat && lng) {
        sessionStorage.setItem('nav_lat', lat);
        sessionStorage.setItem('nav_lng', lng);
    }


if (typeof enviarAvisoDeNavegacion === 'function') {

    const pedidoId   = form.querySelector('input[name="pedido_id"]')?.value;
    const cliente    = form.querySelector('input[name="cliente"]')?.value;
    const celular    = form.querySelector('input[name="celular"]')?.value;
    const motoqueroId= form.querySelector('input[name="motoquero_id"]')?.value;

    enviarAvisoDeNavegacion(
        pedidoId,
        cliente,
        celular,
        motoqueroId
    );
}

    // 🔥 SUBMIT REAL
    form.submit();

    // Cerrar modal
    const modalEl = document.getElementById('modalEmergencia');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();

    pedidoEmergenciaId = null;
});
</script>


<script>
setInterval(() => {
    fetch("{{ route('motoquero.check.emergencia') }}", {
        headers: {
            'Accept': 'application/json'
        },
        redirect: 'manual'
    })
    .then(res => {
        // ❌ Nunca seguir redirects
        if (res.status === 302) return null;
        if (res.status === 204) return null;
        if (res.status === 401) return null;

        return res.json();
    })
    .then(data => {
        if (!data || !data.emergencia) return;
        mostrarModalEmergencia(data);
    })
    .catch(() => {});
}, 5000);
</script>


<script>
let watchId = null;
let ultimaPosicion = null;
let ultimoEnvio = 0;

function iniciarTrackingMotoquero(motoqueroId) {

    if (!navigator.geolocation) {
        console.error('GPS no soportado');
        return;
    }

    // evitar duplicar watchPosition
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
    }

    watchId = navigator.geolocation.watchPosition(

        position => {

            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;

            const ahora = Date.now();

            // evitar spam al servidor
            if (ahora - ultimoEnvio < 4000) {
                return;
            }

            ultimoEnvio = ahora;

            // evitar enviar si no cambió posición
            if (ultimaPosicion) {

                const distancia =
                    Math.abs(lat - ultimaPosicion.lat) +
                    Math.abs(lng - ultimaPosicion.lng);

                if (distancia < 0.00005) {
                    return;
                }
            }

            ultimaPosicion = { lat, lng };

            enviarUbicacionServidor(motoqueroId, lat, lng, accuracy);

        },


        let gpsErrorMostrado = false;

        error => {

            console.error('Error GPS:', error);

            if (error.code === error.PERMISSION_DENIED && !gpsErrorMostrado) {

                gpsErrorMostrado = true;

                Swal.fire({
                    icon: 'warning',
                    title: 'GPS desactivado',
                    text: 'Debes permitir el acceso al GPS para que el sistema funcione correctamente',
                    confirmButtonColor: '#3085d6'
                });

            }

        },


        {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 15000
        }

    );

    // 🔥 respaldo por si watchPosition se congela
    setInterval(() => {

        if (!ultimaPosicion) return;

        enviarUbicacionServidor(
            motoqueroId,
            ultimaPosicion.lat,
            ultimaPosicion.lng,
            0
        );

    }, 15000);
}


function enviarUbicacionServidor(motoqueroId, lat, lng, accuracy) {

    fetch("{{ route('admin.motoquero.ubicacion') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN':
                document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            motoquero_id: motoqueroId,
            latitud: lat,
            longitud: lng,
            accuracy: accuracy,
            timestamp: Date.now()
        })
    })
    .catch(err => console.error('Error enviando ubicación', err));
}


</script>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const motoqueroId = {{ auth()->user()->motoquero->id }};

    if (!navigator.permissions) {
        iniciarTrackingMotoquero(motoqueroId);
        return;
    }

    navigator.permissions.query({ name: 'geolocation' })
        .then(function(permissionStatus) {

            if (permissionStatus.state === 'granted') {

                // Ya tiene permiso
                iniciarTrackingMotoquero(motoqueroId);

            } else if (permissionStatus.state === 'prompt') {

                // Solo una vez pedirá permiso
                iniciarTrackingMotoquero(motoqueroId);

            } else if (permissionStatus.state === 'denied') {

                console.warn("GPS bloqueado por el usuario");

            }

        });

});

</script>


<script>

let sonidoActivo = null;
let intervaloVibracion = null;

function iniciarAlerta(tipo) {

    // 🔊 Elegir sonido según tipo
    if (tipo === 'ya_sale') {
        sonidoActivo = document.getElementById('sound-ya-sale');
    }

    if (tipo === 'no_contesta') {
        sonidoActivo = document.getElementById('sound-no-contesta');
    }

    // 🔊 SONIDO EN LOOP
    if (sonidoActivo) {
        sonidoActivo.currentTime = 0;
        sonidoActivo.loop = true;
        sonidoActivo.play().catch(() => {});
    }

    // 📳 VIBRACIÓN CONTINUA
    if (navigator.vibrate) {
        intervaloVibracion = setInterval(() => {
            navigator.vibrate([500, 300, 500]);
        }, 1200);
    }
}

function detenerAlerta() {

    // 🔇 Detener sonido
    if (sonidoActivo) {
        sonidoActivo.pause();
        sonidoActivo.currentTime = 0;
        sonidoActivo.loop = false;
    }

    // 📳 Detener vibración
    if (intervaloVibracion) {
        clearInterval(intervaloVibracion);
        intervaloVibracion = null;
    }

    if (navigator.vibrate) {
        navigator.vibrate(0);
    }
}

function checkAvisos() {

    fetch("{{ route('admin.motoquero.avisos') }}", {
        headers: {
            'Accept': 'application/json'
        },
        redirect: 'manual'
    })
    .then(res => {

        if (res.status === 302) return null;
        if (res.status === 204) return null;
        if (res.status === 401) return null;
        if (!res.ok) return null;

        return res.json();
    })
    .then(data => {

        if (!data || data.length === 0) return;

        data.forEach(aviso => {

            iniciarAlerta(aviso.tipo);

            let titulo = '';
            let color = '';
            let mensaje = '';

            if (aviso.tipo === 'ya_sale') {
                titulo = '🚀 Cliente en salida';
                color = '#28a745';
                mensaje = '<span style="color:green;font-weight:bold;">EL CLIENTE YA SALE</span>';
            }

            if (aviso.tipo === 'no_contesta') {
                titulo = '📞 Cliente no responde';
                color = '#dc3545';
                mensaje = '<span style="color:red;font-weight:bold;">NO CONTESTA – TOCAR PUERTA</span>';
            }

            Swal.fire({
                icon: 'info',
                title: titulo,
                html: `
                    <b>Pedido:</b> #${aviso.pedido_id}<br>
                    <b>Cliente:</b> ${aviso.cliente}<br><br>
                    ${mensaje}
                `,
                confirmButtonText: 'Entendido',
                confirmButtonColor: color,
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                detenerAlerta();
            });

        });

    })
    .catch(() => {});
}

// ⏱ Polling cada 5 segundos
setInterval(checkAvisos, 5000);

</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function cargarPrecios(){

        document.querySelectorAll('[data-precio-box]').forEach(box => {

            if(box.dataset.cargado === "1") return;

            const clienteId = box.dataset.cliente;

            function obtenerPrecio(productoId, claseSpan) {

                fetch(`/admin/pedidos/precio-cliente/${clienteId}/${productoId}`)
                    .then(res => res.json())
                    .then(data => {

                        if (data && data.precio) {
                            box.querySelector(claseSpan).innerText = data.precio + ' Bs';
                        } else {
                            box.querySelector(claseSpan).innerText = '—';
                        }

                    })
                    .catch(() => {
                        box.querySelector(claseSpan).innerText = '—';
                    });
            }

            obtenerPrecio(1,'.precio-id-1');
            obtenerPrecio(2,'.precio-id-2');

            box.dataset.cargado = "1";

        });

    }

    cargarPrecios();

    setInterval(cargarPrecios,4000);

});
</script>


<script>
document.addEventListener('click', function(e){

    // Abrir selector cámara
    if(e.target.classList.contains('btn-subir-imagen')){

        const clienteId = e.target.dataset.cliente;
        const form = document.querySelector(
            `.form-imagen-casa[data-cliente="${clienteId}"]`
        );

        form.querySelector('input[name="imagen_casa"]').click();
    }
});


document.addEventListener('change', function(e){

    if(e.target.name === 'imagen_casa'){

        const file = e.target.files[0];
        if(!file) return;

        const form = e.target.closest('form');
        const clienteId = form.dataset.cliente;

        const reader = new FileReader();

        reader.onload = function(event){

            const img = new Image();
            img.src = event.target.result;

            img.onload = function(){

                const canvas = document.createElement('canvas');
                const MAX_WIDTH = 1000;

                let width = img.width;
                let height = img.height;

                if(width > MAX_WIDTH){
                    height *= MAX_WIDTH / width;
                    width = MAX_WIDTH;
                }

                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function(blob){

                    if(blob.size > 500 * 1024){
                        alert("La imagen supera 500KB.");
                        return;
                    }

                    const formData = new FormData();
                    formData.append('imagen_casa', blob, 'imagen.jpg');

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN':
                                document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {

                        if(data.success){

                            // Actualizar preview sin recargar
                            const preview = document.querySelector(
                                `.imagen-casa-preview[data-cliente="${clienteId}"]`
                            );

                            preview.outerHTML =
                                `<img src="${data.ruta}"
                                      class="imagen-casa-preview"
                                      data-cliente="${clienteId}"
                                      style="width:120px;height:120px;object-fit:cover;
                                             border-radius:8px;cursor:pointer;
                                             border:1px solid #ddd;">`;

                        }
                    });

                }, 'image/jpeg', 0.7); // calidad 70%
            };
        };

        reader.readAsDataURL(file);
    }
});
</script>

<script>
document.addEventListener('click', function(e){

    // Abrir modal si hacen click en imagen
    if(e.target.classList.contains('imagen-casa-preview')
        && e.target.tagName === 'IMG'){

        const modal = document.getElementById('modalImagenCasa');
        const imgModal = document.getElementById('imagenModalGrande');

        imgModal.src = e.target.src;
        modal.style.display = 'flex';
    }

    // Cerrar con X
    if(e.target.id === 'cerrarModalImagen'){
        document.getElementById('modalImagenCasa').style.display = 'none';
    }

    // Cerrar tocando fondo oscuro
    if(e.target.id === 'modalImagenCasa'){
        document.getElementById('modalImagenCasa').style.display = 'none';
    }

});
</script>


<script>
function marcarPedidoEntregando(pedidoId){

    // quitar resaltado anterior
    document.querySelectorAll('.pedido-entregando').forEach(p => {

        p.classList.remove('pedido-entregando');

        const badge = p.querySelector('.badge-entregando');

        if(badge){
            badge.remove();
        }

    });

    const pedido = document.getElementById('pedido-' + pedidoId);

    if(!pedido) return;

    pedido.classList.add('pedido-entregando');

    const badge = document.createElement('div');
    badge.className = "badge-entregando";
    badge.innerText = "ENTREGANDO";

    pedido.appendChild(badge);

}
</script>

<script>

const pedidosMapa = [

@foreach($pedidosNuevos as $pedido)

{
    id: {{ $pedido->id }},
    orden: {{ $pedido->orden }},
    cliente: "{{ $pedido->cliente->nombre ?? 'Cliente' }}",
    lat: {{ $pedido->cliente->latitud ?? 'null' }},
    lng: {{ $pedido->cliente->longitud ?? 'null' }}
},

@endforeach

];

</script>


<script>
let mapaPedidos;
let marcadoresMapa = [];

function iniciarMapaPedidos(){

    mapaPedidos = new google.maps.Map(
        document.getElementById("mapaPedidosAsignados"),
        {
            center:{lat:-17.7833,lng:-63.1821},
            zoom:12
        }
    );

    const bounds = new google.maps.LatLngBounds();

    pedidosMapa.forEach((p, index) => {

        if(!p.lat || !p.lng) return;

        const posicion = {
            lat:parseFloat(p.lat),
            lng:parseFloat(p.lng)
        };

        const marcador = new google.maps.Marker({
            position:posicion,
            map:mapaPedidos,
            title:p.cliente,
            label: {
                text: p.orden.toString(),
                color: "white",
                fontWeight: "bold"
            }

        });

        const info = new google.maps.InfoWindow({
            content:`
                <b>Orden ${p.orden}</b><br>
                Pedido #${p.id}<br>
                ${p.cliente}
            `
        });

        marcador.addListener("click",()=>{
            info.open(mapaPedidos,marcador);
        });

        bounds.extend(posicion);

    });

    if(!bounds.isEmpty()){
        mapaPedidos.fitBounds(bounds);
    }

}



document.addEventListener("DOMContentLoaded", function(){

    if(document.getElementById("mapaPedidosAsignados")){
        iniciarMapaPedidos();
    }

});


function actualizarMarcadoresMapa(){

    if(!mapaPedidos) return;

    // borrar marcadores anteriores
    marcadoresMapa.forEach(m => m.setMap(null));
    marcadoresMapa = [];

    const bounds = new google.maps.LatLngBounds();

    document.querySelectorAll('.btn-navegar').forEach(btn => {

        const lat = btn.dataset.lat;
        const lng = btn.dataset.lng;
        const cliente = btn.dataset.cliente;
        const orden = btn.dataset.orden || "?";

        if(!lat || !lng || lat === '-' || lng === '-') return;

        const posicion = {
            lat: parseFloat(lat),
            lng: parseFloat(lng)
        };

        const marker = new google.maps.Marker({
            position: posicion,
            map: mapaPedidos,
            label:{
                text:String(orden),
                color:"white",
                fontWeight:"bold"
            }
        });

        const info = new google.maps.InfoWindow({
            content:`<b>Orden ${orden}</b><br>${cliente}`
        });

        marker.addListener("mouseover",()=>{
            info.open(mapaPedidos,marker);
        });

        marker.addListener("mouseout",()=>{
            info.close();
        });

        marcadoresMapa.push(marker);

        bounds.extend(posicion);

    });

    // centrar mapa solo si hay marcadores
    if(marcadoresMapa.length > 0){
        mapaPedidos.fitBounds(bounds);
    }

}


function toggleMapa(){

    const mapa = document.getElementById("contenedorMapaPedidos");
    const boton = document.getElementById("btnToggleMapa");

    if(mapa.style.display === "none"){

        mapa.style.display = "block";
        boton.innerText = "Ocultar mapa";

        localStorage.setItem("mapaVisible","1");

    }else{

        mapa.style.display = "none";
        boton.innerText = "Mostrar mapa";

        localStorage.setItem("mapaVisible","0");

    }

}


document.addEventListener("DOMContentLoaded", function(){

    const estado = localStorage.getItem("mapaVisible");

    if(estado === "0"){

        document.getElementById("contenedorMapaPedidos").style.display = "none";
        document.getElementById("btnToggleMapa").innerText = "Mostrar mapa";

    }

});



</script>


<script>

document.addEventListener('click', function(e){

    const btn = e.target.closest('.btn-rechazar');

    if(!btn) return;

    const formId = btn.dataset.form;

    Swal.fire({
        title: 'Rechazar pedido',
        text: '¿Estás seguro de que deseas rechazar este pedido?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, rechazar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {

        if (result.isConfirmed) {
            document.getElementById(formId).submit();
        }

    });

});
</script>


<script>
document.querySelectorAll('.btnPagadoDistribuidor').forEach(btn => {
    btn.addEventListener('click', function () {

        let id = this.dataset.id;

        Swal.fire({
            title: '¿Confirmar pago?',
            text: '¿Te pagaron este pedido por QR?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, pagado',
        }).then(result => {

            if (result.isConfirmed) {

                fetch(`/admin/pedidos/${id}/qr-pagado-distribuidor`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    }
                })
                .then(() => location.reload());

            }
        });
    });
});
</script>



<!-- 🔍 MODAL IMAGEN GRANDE -->
<div id="modalImagenCasa" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.85);
    justify-content:center;
    align-items:center;
    z-index:9999;
">

    <span id="cerrarModalImagen"
          style="
            position:absolute;
            top:20px;
            right:25px;
            font-size:30px;
            color:white;
            cursor:pointer;
          ">
        &times;
    </span>

    <img id="imagenModalGrande"
         src=""
         style="
            max-width:95%;
            max-height:90%;
            border-radius:10px;
            box-shadow:0 0 20px rgba(0,0,0,0.5);
         ">
</div>

@stop