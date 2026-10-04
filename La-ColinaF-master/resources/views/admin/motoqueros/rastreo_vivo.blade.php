@extends('adminlte::page')

@section('title', 'Rastreo en Vivo de Distribuidores')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><b><i class="fas fa-satellite text-danger"></i> Monitoreo GPS en Tiempo Real</b></h1>
        <div>
            <span class="badge badge-success p-2" id="badgeEstado">
                <i class="fas fa-sync-alt fa-spin"></i> Actualizando en vivo (cada 4s)
            </span>
            <span class="badge badge-secondary p-2 ml-2" id="ultimaHora">--:--:--</span>
        </div>
    </div>
    <hr>
@stop

@section('content')
<div class="row">
    <!-- Panel lateral: Lista de Motoqueros -->
    <div class="col-lg-4 col-md-5">
        <div class="card card-outline card-primary shadow-sm" style="height: 75vh; display: flex; flex-direction: column;">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-motorcycle text-primary"></i> Distribuidores Activos
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" onclick="cargarUbicaciones()" title="Actualizar ahora">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-2" style="overflow-y: auto; flex: 1;" id="listaMotoqueros">
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Cargando ubicaciones GPS...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapa Interactivo -->
    <div class="col-lg-8 col-md-7">
        <div class="card card-outline card-success shadow-sm" style="height: 75vh; position: relative;">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-dark">
                    <i class="fas fa-map-marked-alt text-success"></i> Mapa de Distribución en Vivo
                </span>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="ajustarVistaGlobal()">
                        <i class="fas fa-compress-arrows-alt"></i> Ver Todos
                    </button>
                    <button class="btn btn-sm btn-outline-danger ml-1" onclick="limpiarTrazos()" id="btnLimpiarTrazo" style="display:none;">
                        <i class="fas fa-eraser"></i> Ocultar Recorrido
                    </button>
                </div>
            </div>
            <div class="card-body p-0" style="height: calc(100% - 46px);">
                <div id="mapaRastreo" style="width: 100%; height: 100%; min-height: 500px;"></div>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .moto-marker-container {
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .moto-marker-bubble {
        background: #007bff;
        color: #fff;
        border-radius: 50%;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        border: 2px solid #ffffff;
        transition: transform 0.2s ease;
    }
    .moto-marker-bubble.online {
        background: #28a745;
    }
    .moto-marker-bubble.en-camino {
        background: #fd7e14;
    }
    .moto-pulse {
        position: absolute;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(40, 167, 69, 0.4);
        animation: pulseAnimation 2s infinite;
        z-index: -1;
    }
    @keyframes pulseAnimation {
        0% { transform: scale(0.6); opacity: 1; }
        100% { transform: scale(1.4); opacity: 0; }
    }
    .item-motoquero {
        cursor: pointer;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        margin-bottom: 8px;
        padding: 10px 12px;
        transition: all 0.2s;
    }
    .item-motoquero:hover {
        background-color: #f1f8ff;
        border-color: #b8daff;
        transform: translateY(-2px);
    }
    .item-motoquero.active-item {
        border-color: #28a745;
        background-color: #eafaf1;
    }
</style>
@stop

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map;
    let markers = {};
    let polylineRecorrido = null;
    let motoquerosData = [];
    let focusedMotoqueroId = null;

    // Inicializar mapa Leaflet centrado en Santa Cruz / Bolivia
    document.addEventListener("DOMContentLoaded", function() {
        map = L.map('mapaRastreo').setView([-17.7833, -63.1821], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        cargarUbicaciones();
        // Polling en tiempo real cada 4 segundos
        setInterval(cargarUbicaciones, 4000);
    });

    function cargarUbicaciones() {
        fetch("{{ url('/api/motoqueros/ubicaciones') }}")
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                document.getElementById('ultimaHora').textContent = 'Hora: ' + data.servidor_hora;
                motoquerosData = data.motoqueros;
                renderListaMotoqueros(motoquerosData);
                actualizarMarcadores(motoquerosData);
            })
            .catch(err => console.error("Error al obtener ubicaciones GPS:", err));
    }

    function renderListaMotoqueros(motoqueros) {
        const contenedor = document.getElementById('listaMotoqueros');
        if (!motoqueros || motoqueros.length === 0) {
            contenedor.innerHTML = '<div class="text-center py-4 text-muted">No hay distribuidores registrados.</div>';
            return;
        }

        let html = '';
        motoqueros.forEach(m => {
            const hasGps = m.latitud && m.longitud;
            const statusClass = m.online ? 'badge-success' : 'badge-secondary';
            const statusText = m.online ? 'En Línea' : 'Desconectado';
            const activePedido = m.pedido_actual;

            html += `
                <div class="item-motoquero ${focusedMotoqueroId === m.motoquero_id ? 'active-item' : ''}" onclick="centrarEnMotoquero(${m.motoquero_id})">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark"><i class="fas fa-user-circle text-primary mr-1"></i> ${m.nombre}</strong>
                        <span class="badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="small text-muted mb-1">
                        <i class="fas fa-motorcycle"></i> Placa: <b>${m.placa}</b> | 
                        <i class="fas fa-phone-alt"></i> ${m.celular}
                    </div>

                    ${activePedido ? `
                        <div class="p-2 mb-1 rounded bg-light border border-warning small">
                            <span class="text-warning font-weight-bold"><i class="fas fa-box"></i> ${activePedido.orden ? 'Parada #' + activePedido.orden + ' • ' : ''}Pedido #${activePedido.id} en camino:</span><br>
                            <b>Cliente:</b> ${activePedido.cliente_nombre}<br>
                            <b>Dir:</b> ${activePedido.cliente_direccion || 'Sin dirección'}<br>
                            <b>Monto:</b> Bs. ${activePedido.total_precio}
                        </div>
                    ` : `
                        <div class="small text-muted mb-1"><i class="fas fa-check-circle text-success"></i> Disponible para pedidos</div>
                    `}

                    <div class="d-flex justify-content-between align-items-center mt-2 small">
                        <span><i class="fas fa-satellite"></i> ${hasGps ? m.registrado_en : 'Sin señal GPS'}</span>
                        ${hasGps ? `
                            <button class="btn btn-xs btn-primary" onclick="event.stopPropagation(); verRecorrido(${m.motoquero_id})">
                                <i class="fas fa-route"></i> Ver Recorrido
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        contenedor.innerHTML = html;
    }

    function actualizarMarcadores(motoqueros) {
        motoqueros.forEach(m => {
            if (!m.latitud || !m.longitud) return;

            const latLng = [m.latitud, m.longitud];
            const isEnCamino = !!m.pedido_actual;
            const bubbleColor = isEnCamino ? 'en-camino' : (m.online ? 'online' : '');

            // Icono HTML personalizado
            const iconHtml = `
                <div class="moto-marker-container">
                    ${m.online ? '<div class="moto-pulse"></div>' : ''}
                    <div class="moto-marker-bubble ${bubbleColor}">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                </div>
            `;

            const customIcon = L.divIcon({
                html: iconHtml,
                className: '',
                iconSize: [40, 40],
                iconAnchor: [20, 20],
                popupAnchor: [0, -20]
            });

            const popupContent = `
                <div style="min-width: 200px;">
                    <h6 class="font-weight-bold mb-1 text-primary"><i class="fas fa-motorcycle"></i> ${m.nombre}</h6>
                    <small><b>Placa:</b> ${m.placa} | <b>Tel:</b> ${m.celular}</small><hr class="my-1">
                    ${m.pedido_actual ? `
                        <div class="alert alert-warning p-1 mb-2 small">
                            <b>Entrega en curso:</b> #${m.pedido_actual.id}<br>
                            <b>Cliente:</b> ${m.pedido_actual.cliente_nombre}<br>
                            <b>Total:</b> Bs. ${m.pedido_actual.total_precio}
                        </div>
                    ` : '<span class="text-success small font-weight-bold">Disponible sin pedidos activos</span><br>'}
                    <small class="text-muted"><i class="fas fa-clock"></i> Último reporte: ${m.registrado_en}</small><br>
                    <button class="btn btn-xs btn-block btn-info mt-2" onclick="verRecorrido(${m.motoquero_id})">
                        <i class="fas fa-route"></i> Ver trayecto de hoy
                    </button>
                </div>
            `;

            if (markers[m.motoquero_id]) {
                markers[m.motoquero_id].setLatLng(latLng);
                markers[m.motoquero_id].setIcon(customIcon);
                markers[m.motoquero_id].setPopupContent(popupContent);
            } else {
                const marker = L.marker(latLng, { icon: customIcon }).addTo(map);
                marker.bindPopup(popupContent);
                markers[m.motoquero_id] = marker;
            }
        });

        // Si tenemos un motoquero enfocado, re-centrar
        if (focusedMotoqueroId && markers[focusedMotoqueroId]) {
            map.panTo(markers[focusedMotoqueroId].getLatLng());
        }
    }

    function centrarEnMotoquero(id) {
        focusedMotoqueroId = id;
        renderListaMotoqueros(motoquerosData);

        if (markers[id]) {
            map.flyTo(markers[id].getLatLng(), 16, { animate: true, duration: 1.2 });
            markers[id].openPopup();
        } else {
            alert("Este distribuidor aún no ha transmitido coordenadas GPS hoy.");
        }
    }

    function ajustarVistaGlobal() {
        focusedMotoqueroId = null;
        renderListaMotoqueros(motoquerosData);

        const latLngs = Object.values(markers).map(m => m.getLatLng());
        if (latLngs.length > 0) {
            const bounds = L.latLngBounds(latLngs);
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }

    function verRecorrido(motoqueroId) {
        fetch("{{ url('/admin/pedidos/motoquero') }}/" + motoqueroId + "/recorrido")
            .then(res => res.json())
            .then(puntos => {
                limpiarTrazos();

                if (!puntos || puntos.length === 0) {
                    alert("No hay historial de recorrido para este distribuidor hoy.");
                    return;
                }

                const latLngs = puntos.map(p => [parseFloat(p.latitud), parseFloat(p.longitud)]);
                polylineRecorrido = L.polyline(latLngs, {
                    color: '#e74c3c',
                    weight: 5,
                    opacity: 0.85,
                    dashArray: '8, 8'
                }).addTo(map);

                document.getElementById('btnLimpiarTrazo').style.display = 'inline-block';
                map.fitBounds(polylineRecorrido.getBounds(), { padding: [40, 40] });
            })
            .catch(err => console.error("Error al obtener recorrido:", err));
    }

    function limpiarTrazos() {
        if (polylineRecorrido) {
            map.removeLayer(polylineRecorrido);
            polylineRecorrido = null;
        }
        document.getElementById('btnLimpiarTrazo').style.display = 'none';
    }
</script>
@stop
