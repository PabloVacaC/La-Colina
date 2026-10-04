import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;

import '../models/user_model.dart';
import '../models/pedido_model.dart';
import '../services/api_service.dart';

class MapaRutaScreen extends StatefulWidget {
  final UserSession session;
  final List<Pedido> pedidos;
  final VoidCallback onPedidosActualizados;
  final Pedido? pedidoInicial;

  const MapaRutaScreen({
    super.key,
    required this.session,
    required this.pedidos,
    required this.onPedidosActualizados,
    this.pedidoInicial,
  });

  @override
  State<MapaRutaScreen> createState() => _MapaRutaScreenState();
}

class _MapaRutaScreenState extends State<MapaRutaScreen> {
  final MapController _mapController = MapController();
  final ApiService _api = ApiService();
  final ImagePicker _picker = ImagePicker();

  LatLng? _posicionMoto;
  StreamSubscription<Position>? _positionStream;

  Pedido? _pedidoActivo;
  List<LatLng> _puntosRutaCalle = [];
  bool _cargandoRuta = false;
  double? _distanciaKm;
  DateTime? _ultimoCalculoRuta;

  @override
  void initState() {
    super.initState();
    _iniciarSeguimientoGps();
    _seleccionarPedidoInicial();
  }

  @override
  void didUpdateWidget(covariant MapaRutaScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Si la lista de pedidos cambió, actualizar el destino activo si fue completado
    if (_pedidoActivo != null) {
      final aunExiste = _pedidosPendientes.any((p) => p.id == _pedidoActivo!.id);
      if (!aunExiste) {
        _seleccionarPedidoInicial();
      }
    } else {
      _seleccionarPedidoInicial();
    }
  }

  @override
  void dispose() {
    _positionStream?.cancel();
    super.dispose();
  }

  /// Filtra solo los pedidos pendientes con coordenadas válidas de este motoquero
  List<Pedido> get _pedidosPendientes {
    return widget.pedidos.where((p) {
      final estadoValido = p.estado != 'Entregado';
      final tieneCoords = p.cliente?.latitud != null && p.cliente?.longitud != null;
      return estadoValido && tieneCoords;
    }).toList()
      ..sort((a, b) {
        // Primero pedidos "En camino", luego por orden
        if (a.estado == 'En camino' && b.estado != 'En camino') return -1;
        if (b.estado == 'En camino' && a.estado != 'En camino') return 1;
        return a.orden.compareTo(b.orden);
      });
  }

  void _seleccionarPedidoInicial() {
    final pendientes = _pedidosPendientes;
    if (pendientes.isEmpty) {
      setState(() {
        _pedidoActivo = null;
        _puntosRutaCalle = [];
        _distanciaKm = null;
      });
      return;
    }

    if (widget.pedidoInicial != null && pendientes.any((p) => p.id == widget.pedidoInicial!.id)) {
      _pedidoActivo = widget.pedidoInicial;
    } else {
      _pedidoActivo = pendientes.first;
    }

    _calcularRutaCalle();
  }

  /// Escucha el GPS de la moto en tiempo real
  Future<void> _iniciarSeguimientoGps() async {
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      if (mounted) {
        setState(() {
          _posicionMoto = LatLng(pos.latitude, pos.longitude);
        });
        _calcularRutaCalle();
      }
    } catch (_) {}

    _positionStream = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10, // cada 10 metros
      ),
    ).listen((pos) {
      if (mounted) {
        final moto = LatLng(pos.latitude, pos.longitude);
        setState(() {
          _posicionMoto = moto;
        });
        _evaluarRecalculoRuta(moto);
      }
    });
  }

  /// Evalúa si es necesario recalcular la ruta sobre calles (evita saturar OSRM en cada paso de GPS)
  void _evaluarRecalculoRuta(LatLng moto) {
    if (_pedidoActivo == null) return;
    final cliente = _pedidoActivo!.cliente;
    if (cliente == null || cliente.latitud == null || cliente.longitud == null) return;

    if (_puntosRutaCalle.isEmpty) {
      _calcularRutaCalle();
      return;
    }

    final ahora = DateTime.now();
    final segDesdeUltimo = _ultimoCalculoRuta == null
        ? 999
        : ahora.difference(_ultimoCalculoRuta!).inSeconds;

    if (segDesdeUltimo < 15) return;

    // Detectar si hubo desvío (> 75 metros)
    double distMinima = double.infinity;
    for (final p in _puntosRutaCalle) {
      final d = Geolocator.distanceBetween(moto.latitude, moto.longitude, p.latitude, p.longitude);
      if (d < distMinima) distMinima = d;
      if (distMinima <= 75) break;
    }

    final seDesvio = distMinima > 75;

    if (seDesvio || segDesdeUltimo >= 60) {
      _calcularRutaCalle();
    }
  }

  /// Consulta la ruta exacta sobre calles usando OSRM (gratuito)
  Future<void> _calcularRutaCalle({bool forzar = false}) async {
    if (_posicionMoto == null || _pedidoActivo == null) return;
    if (_cargandoRuta && !forzar) return;

    final cliente = _pedidoActivo!.cliente;
    if (cliente == null || cliente.latitud == null || cliente.longitud == null) return;

    _ultimoCalculoRuta = DateTime.now();
    final destino = LatLng(cliente.latitud!, cliente.longitud!);

    setState(() => _cargandoRuta = true);

    try {
      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/'
        '${_posicionMoto!.longitude},${_posicionMoto!.latitude};'
        '${destino.longitude},${destino.latitude}'
        '?overview=full&geometries=geojson',
      );

      final resp = await http.get(url).timeout(const Duration(seconds: 4));
      if (resp.statusCode == 200) {
        final data = json.decode(resp.body);
        final routes = data['routes'] as List?;
        if (routes != null && routes.isNotEmpty) {
          final geom = routes[0]['geometry'];
          final coords = geom['coordinates'] as List;
          final distMetros = (routes[0]['distance'] as num?)?.toDouble() ?? 0.0;

          if (mounted) {
            setState(() {
              _puntosRutaCalle = coords
                  .map((c) => LatLng((c[1] as num).toDouble(), (c[0] as num).toDouble()))
                  .toList();
              _distanciaKm = distMetros / 1000.0;
              _cargandoRuta = false;
            });
            return;
          }
        }
      }
    } catch (e) {
      debugPrint('Fallo OSRM, usando línea directa: $e');
    }

    // Fallback: cálculo de distancia en línea recta y trazo simple
    if (mounted) {
      final distMetros = Geolocator.distanceBetween(
        _posicionMoto!.latitude,
        _posicionMoto!.longitude,
        destino.latitude,
        destino.longitude,
      );
      setState(() {
        _puntosRutaCalle = [_posicionMoto!, destino];
        _distanciaKm = distMetros / 1000.0;
        _cargandoRuta = false;
      });
    }
  }

  void _centrarEnMoto() {
    if (_posicionMoto != null) {
      _mapController.move(_posicionMoto!, 16.0);
    }
  }

  void _ajustarVistaRutaCompleta() {
    final puntos = <LatLng>[];
    if (_posicionMoto != null) puntos.add(_posicionMoto!);

    for (final p in _pedidosPendientes) {
      if (p.cliente?.latitud != null && p.cliente?.longitud != null) {
        puntos.add(LatLng(p.cliente!.latitud!, p.cliente!.longitud!));
      }
    }

    if (puntos.isEmpty) return;

    if (puntos.length == 1) {
      _mapController.move(puntos.first, 15.0);
      return;
    }

    final bounds = LatLngBounds.fromPoints(puntos);
    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: bounds,
        padding: const EdgeInsets.only(top: 80, bottom: 260, left: 40, right: 40),
      ),
    );
  }

  Future<void> _abrirEnNavegadorExterno(Pedido pedido) async {
    final cliente = pedido.cliente;
    if (cliente?.latitud == null || cliente?.longitud == null) return;

    final lat = cliente!.latitud!;
    final lng = cliente.longitud!;

    // Abrir directamente en Google Maps con modo navegación en moto/auto
    final googleMapsUrl = Uri.parse('google.navigation:q=$lat,$lng&mode=d');
    final webUrl = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving');

    try {
      if (await canLaunchUrl(googleMapsUrl)) {
        await launchUrl(googleMapsUrl);
      } else {
        await launchUrl(webUrl, mode: LaunchMode.externalApplication);
      }
    } catch (_) {
      await launchUrl(webUrl, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _llamarCliente(String telefono) async {
    final clean = telefono.replaceAll(RegExp(r'[^0-9+]'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _tomarPedido(Pedido pedido) async {
    final success = await _api.tomarPedido(pedido.id);
    if (success) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Color(0xFF00C853),
            content: Text('¡Pedido en camino! Sigue la ruta marcada.'),
          ),
        );
      }
      widget.onPedidosActualizados();
      setState(() {
        pedido.estado = 'En camino';
      });
      _calcularRutaCalle(forzar: true);
    }
  }

  Future<void> _subirFotoCasa(Pedido pedido) async {
    final cliente = pedido.cliente;
    if (cliente == null) return;

    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Color(0xFF1E88E5)),
              title: const Text('Tomar foto con la cámara'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: Color(0xFF43A047)),
              title: const Text('Elegir de la galería'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (source == null) return;

    final picked = await _picker.pickImage(source: source, imageQuality: 75);
    if (picked == null) return;

    final newUrl = await _api.subirFotoCasa(cliente.id, picked);
    if (newUrl != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Color(0xFF00C853),
          content: Text('Foto de la entrega subida correctamente.'),
        ),
      );
      widget.onPedidosActualizados();
    }
  }

  Future<void> _finalizarEntrega(Pedido pedido) async {
    String metodo = 'Efectivo';

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDlg) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.check_circle, color: Color(0xFF00C853)),
              SizedBox(width: 8),
              Text('Confirmar Entrega'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Pedido #${pedido.id} • ${pedido.cliente?.nombre ?? ""}'),
              const SizedBox(height: 8),
              Text(
                'Total a cobrar: Bs. ${pedido.totalPrecio.toStringAsFixed(2)}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const Divider(height: 24),
              const Text('Método de Pago:', style: TextStyle(fontWeight: FontWeight.w600)),
              RadioListTile<String>(
                title: const Text('Efectivo'),
                value: 'Efectivo',
                groupValue: metodo,
                onChanged: (v) => setDlg(() => metodo = v!),
              ),
              RadioListTile<String>(
                title: const Text('QR'),
                value: 'QR',
                groupValue: metodo,
                onChanged: (v) => setDlg(() => metodo = v!),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF00C853),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Registrar Entrega', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      ),
    );

    if (confirm != true) return;
    if (pedido.estado == 'Entregado') return;

    final success = await _api.finalizarPedido(pedido.id, metodo);
    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFF00C853),
          content: Text('¡Pedido #${pedido.id} entregado con éxito! Pasando a la siguiente parada...'),
          duration: const Duration(seconds: 3),
        ),
      );

      // Notificar para refrescar pedidos en la app
      widget.onPedidosActualizados();

      // Transición automática al siguiente destino
      setState(() {
        pedido.estado = 'Entregado';
        _seleccionarPedidoInicial();
      });
    }
  }

  Future<void> _cancelarEntrega(Pedido pedido) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Color(0xFFD32F2F), size: 26),
            SizedBox(width: 8),
            Text('Cancelar Entrega', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          ],
        ),
        content: Text('¿Deseas cancelar la entrega del Pedido #${pedido.id} (${pedido.cliente?.nombre ?? "Cliente"})? El pedido se retirará de tu ruta y pasarás a la siguiente ubicación.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Volver', style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFD32F2F),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Sí, Cancelar Pedido', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    final success = await _api.cancelarPedido(pedido.id, motivo: 'Cliente no sale / no atiende');
    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFFD32F2F),
          content: Text('Entrega del pedido #${pedido.id} cancelada. Pasando a la siguiente parada...'),
          duration: const Duration(seconds: 3),
        ),
      );

      widget.pedidos.removeWhere((p) => p.id == pedido.id);
      widget.onPedidosActualizados();

      setState(() {
        _seleccionarPedidoInicial();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final pendientes = _pedidosPendientes;
    final puntoInicial = _posicionMoto ??
        (_pedidoActivo?.cliente?.latitud != null
            ? LatLng(_pedidoActivo!.cliente!.latitud!, _pedidoActivo!.cliente!.longitud!)
            : const LatLng(-17.7833, -63.1821)); // Santa Cruz fallback

    return Scaffold(
      body: Stack(
        children: [
          // 1. MAPA INTERACTIVO
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              initialCenter: puntoInicial,
              initialZoom: 15.0,
              minZoom: 5.0,
              maxZoom: 19.0,
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'com.lacolina.motoquero.motoquero_app',
              ),

              // Trazado de ruta activa (hacia el cliente actual)
              if (_puntosRutaCalle.isNotEmpty)
                PolylineLayer(
                  polylines: [
                    Polyline(
                      points: _puntosRutaCalle,
                      strokeWidth: 5.5,
                      color: const Color(0xFF1E88E5),
                      borderColor: const Color(0xFF0D47A1),
                      borderStrokeWidth: 1.5,
                    ),
                  ],
                ),

              // Marcadores en el mapa
              MarkerLayer(
                markers: [
                  // Marcador de la MOTO del motoquero
                  if (_posicionMoto != null)
                    Marker(
                      point: _posicionMoto!,
                      width: 52,
                      height: 52,
                      child: Container(
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: const Color(0xFF00C853),
                          border: Border.all(color: Colors.white, width: 3),
                          boxShadow: const [
                            BoxShadow(
                              color: Colors.black26,
                              blurRadius: 8,
                              offset: Offset(0, 4),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.two_wheeler,
                          color: Colors.white,
                          size: 28,
                        ),
                      ),
                    ),

                  // Marcadores de cada parada del motoquero
                  ...pendientes.asMap().entries.map((entry) {
                    final index = entry.key;
                    final p = entry.value;
                    final cl = p.cliente!;
                    final latLng = LatLng(cl.latitud!, cl.longitud!);
                    final esActivo = _pedidoActivo?.id == p.id;

                    return Marker(
                      point: latLng,
                      width: esActivo ? 64 : 46,
                      height: esActivo ? 64 : 46,
                      child: GestureDetector(
                        onTap: () {
                          setState(() {
                            _pedidoActivo = p;
                          });
                          _calcularRutaCalle(forzar: true);
                        },
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: esActivo ? const Color(0xFFFF3D00) : const Color(0xFF0277BD),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: Colors.white, width: 2),
                                boxShadow: const [
                                  BoxShadow(
                                    color: Colors.black38,
                                    blurRadius: 6,
                                    offset: Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: Text(
                                '#${index + 1}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            Icon(
                              Icons.location_on,
                              color: esActivo ? const Color(0xFFFF3D00) : const Color(0xFF0277BD),
                              size: esActivo ? 38 : 28,
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ],
          ),

          // 2. HEADER SUPERIOR TRASLÚCIDO
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Card(
                elevation: 4,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                color: Colors.white.withValues(alpha: 0.95),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFF00C853).withValues(alpha: 0.15),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.navigation, color: Color(0xFF00C853), size: 22),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Ruta de ${widget.session.motoquero.nombres}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              pendientes.isEmpty
                                  ? 'Sin entregas pendientes'
                                  : '${pendientes.length} entregas programadas',
                              style: TextStyle(
                                color: pendientes.isEmpty ? Colors.grey : const Color(0xFF1E88E5),
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (_cargandoRuta)
                        const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      else if (_distanciaKm != null)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFF1E88E5),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '${_distanciaKm!.toStringAsFixed(1)} km',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          // 3. BOTONES FLOTANTES DE CONTROL DE MAPA
          Positioned(
            right: 16,
            bottom: _pedidoActivo != null ? 240 : 30,
            child: Column(
              children: [
                FloatingActionButton.small(
                  heroTag: 'btnFitRuta',
                  backgroundColor: Colors.white,
                  foregroundColor: const Color(0xFF1E88E5),
                  onPressed: _ajustarVistaRutaCompleta,
                  tooltip: 'Ver toda la ruta',
                  child: const Icon(Icons.zoom_out_map),
                ),
                const SizedBox(height: 8),
                FloatingActionButton.small(
                  heroTag: 'btnMiMoto',
                  backgroundColor: const Color(0xFF00C853),
                  foregroundColor: Colors.white,
                  onPressed: _centrarEnMoto,
                  tooltip: 'Centrar en mi moto',
                  child: const Icon(Icons.my_location),
                ),
              ],
            ),
          ),

          // 4. TARJETA INFERIOR ESTILO GPS (UBER/WAZE)
          if (_pedidoActivo != null)
            Positioned(
              left: 12,
              right: 12,
              bottom: 12,
              child: _buildTarjetaDestino(_pedidoActivo!, pendientes),
            )
          else if (pendientes.isEmpty)
            const Positioned(
              left: 16,
              right: 16,
              bottom: 20,
              child: Card(
                elevation: 6,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16))),
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.check_circle_outline, color: Color(0xFF00C853), size: 48),
                      SizedBox(height: 8),
                      Text(
                        '¡Todas las entregas completadas!',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'No tienes pedidos pendientes en este momento.',
                        style: TextStyle(color: Colors.grey, fontSize: 13),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildTarjetaDestino(Pedido pedido, List<Pedido> pendientes) {
    final cliente = pedido.cliente!;
    final index = pendientes.indexWhere((p) => p.id == pedido.id);
    final numeroParada = index >= 0 ? index + 1 : 1;
    final esEnCamino = pedido.estado == 'En camino';

    return Card(
      elevation: 8,
      shadowColor: Colors.black45,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Cabecera: Parada actual y estado
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: esEnCamino ? const Color(0xFFFF3D00) : const Color(0xFF1E88E5),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    'PARADA ${pedido.orden > 0 ? pedido.orden : numeroParada} • ${pedido.estado.toUpperCase()}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
                const Spacer(),
                Text(
                  'Bs. ${pedido.totalPrecio.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF2E7D32),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),

            // Nombre y Dirección
            Text(
              cliente.nombre,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 2),
            Row(
              children: [
                const Icon(Icons.location_on, size: 14, color: Colors.grey),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    cliente.direccion,
                    style: const TextStyle(color: Colors.black87, fontSize: 13),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const Divider(height: 18),

            // Botones de acción rápida estilo GPS
            Row(
              children: [
                // Botón Llamar
                IconButton.filledTonal(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFFE8F5E9),
                    foregroundColor: const Color(0xFF2E7D32),
                  ),
                  onPressed: () => _llamarCliente(cliente.celular),
                  icon: const Icon(Icons.phone),
                  tooltip: 'Llamar al cliente',
                ),
                const SizedBox(width: 6),

                // Botón Abrir en Google Maps / Waze
                IconButton.filledTonal(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFFE3F2FD),
                    foregroundColor: const Color(0xFF1565C0),
                  ),
                  onPressed: () => _abrirEnNavegadorExterno(pedido),
                  icon: const Icon(Icons.assistant_direction),
                  tooltip: 'Abrir en Google Maps / Waze',
                ),
                const SizedBox(width: 6),

                // Botón Foto Casa
                IconButton.filledTonal(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFFFFF3E0),
                    foregroundColor: const Color(0xFFE65100),
                  ),
                  onPressed: () => _subirFotoCasa(pedido),
                  icon: const Icon(Icons.camera_alt),
                  tooltip: 'Subir foto de la casa',
                ),
                const SizedBox(width: 8),

                // Botón Cancelar Entrega (si el cliente no sale)
                if (esEnCamino) ...[
                  IconButton(
                    style: IconButton.styleFrom(
                      backgroundColor: const Color(0xFFFFEBEE),
                      foregroundColor: const Color(0xFFD32F2F),
                    ),
                    onPressed: () => _cancelarEntrega(pedido),
                    icon: const Icon(Icons.cancel_outlined),
                    tooltip: 'Cancelar entrega (cliente no salió)',
                  ),
                  const SizedBox(width: 8),
                ],

                // Botón Principal: Tomar Pedido o Entregar
                Expanded(
                  child: esEnCamino
                      ? ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF00C853),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          onPressed: () => _finalizarEntrega(pedido),
                          icon: const Icon(Icons.check_circle, size: 18),
                          label: const Text('Entregar', style: TextStyle(fontWeight: FontWeight.bold)),
                        )
                      : ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF1E88E5),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          onPressed: () => _tomarPedido(pedido),
                          icon: const Icon(Icons.two_wheeler, size: 18),
                          label: const Text('Iniciar Ruta', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
