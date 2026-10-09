import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;
import 'package:flutter/foundation.dart';
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
  bool _seguirDistribuidor = true; // Centrado automático al desplazarse
  double _rumboMoto = 0.0; // Rumbo de desplazamiento en grados (0-360°)
  bool _orientarConRumbo = true; // El mapa se orienta dinámicamente según la dirección de avance
  LatLng? _posicionAlPausar; // Ubicación donde se pausó el mapa manualmente
  Timer? _timerAutoReanudarSeguimiento; // Temporizador para auto-reanudar
  Timer? _timerFallbackGps; // Temporizador de respaldo para actualización continua
  StreamSubscription<Position>? _positionStream;

  Pedido? _pedidoActivo;
  List<LatLng> _puntosRutaActiva = [];   // Tramo hacia la siguiente parada (AZUL)
  List<LatLng> _puntosRutaRestante = []; // Tramo hacia el resto de paradas (PLOMO)
  List<LatLng> _puntosRutaCalle = [];    // Total puntos para desvíos y encuadre
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
    _timerFallbackGps?.cancel();
    _timerAutoReanudarSeguimiento?.cancel();
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
        _puntosRutaActiva = [];
        _puntosRutaRestante = [];
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

  Future<bool> _asegurarPermisosGps() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) return false;
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) return false;
      }
      if (permission == LocationPermission.deniedForever) return false;
      return true;
    } catch (_) {
      return false;
    }
  }

  /// Escucha el GPS de la moto en tiempo real con alta frecuencia y seguimiento
  Future<void> _iniciarSeguimientoGps() async {
    await _asegurarPermisosGps();

    // 1. Obtener de inmediato la última ubicación conocida
    try {
      final lastPos = await Geolocator.getLastKnownPosition();
      if (lastPos != null && mounted) {
        _procesarUbicacionGps(lastPos);
      }
    } catch (_) {}

    // 2. Obtener posición GPS actual precisa
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 6),
        ),
      );
      if (mounted) {
        _procesarUbicacionGps(pos);
      }
    } catch (_) {}

    // 3. Escuchar flujo de GPS continuo en tiempo real (alta precisión, sin bloqueos de AndroidSettings)
    const locationSettings = LocationSettings(
      accuracy: LocationAccuracy.high,
      distanceFilter: 2, // cada 2 metros para actualización fluida y precisa
    );

    _positionStream?.cancel();
    _positionStream = Geolocator.getPositionStream(
      locationSettings: locationSettings,
    ).listen((pos) {
      _procesarUbicacionGps(pos);
    }, onError: (err) {
      debugPrint('Error en stream GPS motoquero: $err');
    });

    // 4. Temporizador de respaldo cada 5 segundos para actualización ininterrumpida
    _timerFallbackGps?.cancel();
    _timerFallbackGps = Timer.periodic(const Duration(seconds: 5), (_) async {
      if (!mounted) return;
      try {
        final pos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            timeLimit: Duration(seconds: 4),
          ),
        );
        _procesarUbicacionGps(pos);
      } catch (_) {}
    });
  }

  void _procesarUbicacionGps(Position pos) {
    if (!mounted) return;
    final moto = LatLng(pos.latitude, pos.longitude);
    final motoAnterior = _posicionMoto;

    final double velocidad = pos.speed; // m/s
    final double distMetros = motoAnterior != null
        ? Geolocator.distanceBetween(
            motoAnterior.latitude,
            motoAnterior.longitude,
            moto.latitude,
            moto.longitude,
          )
        : 0.0;

    // Calcular o actualizar rumbo (heading) de desplazamiento cuando hay avance
    if (pos.heading > 0.0 && (velocidad > 0.4 || distMetros > 1.2)) {
      _rumboMoto = pos.heading;
    } else if (distMetros >= 1.5 && motoAnterior != null) {
      final bearing = Geolocator.bearingBetween(
        motoAnterior.latitude,
        motoAnterior.longitude,
        moto.latitude,
        moto.longitude,
      );
      _rumboMoto = (bearing + 360) % 360;
    }

    // Si el seguimiento estaba en pausa pero el motoquero se empezó a mover (> 7m o velocidad > 1.0 m/s):
    if (!_seguirDistribuidor && _posicionAlPausar != null) {
      final distDesdePausa = Geolocator.distanceBetween(
        _posicionAlPausar!.latitude,
        _posicionAlPausar!.longitude,
        moto.latitude,
        moto.longitude,
      );
      if (distDesdePausa > 7.0 || velocidad > 1.0) {
        _timerAutoReanudarSeguimiento?.cancel();
        _seguirDistribuidor = true;
        _posicionAlPausar = null;
      }
    }

    setState(() {
      _posicionMoto = moto;
    });

    // Centrado automático al desplazarse el distribuidor
    if (_seguirDistribuidor) {
      _centrarEnMoto();
    }

    _evaluarRecalculoRuta(moto);
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

  /// Consulta la ruta exacta sobre calles usando OSRM:
  /// Tramo hacia el pedido activo en AZUL, resto de la ruta en PLOMO
  Future<void> _calcularRutaCalle({bool forzar = false}) async {
    if (_posicionMoto == null || _pedidoActivo == null) return;
    if (_cargandoRuta && !forzar) return;

    final cliente = _pedidoActivo!.cliente;
    if (cliente == null || cliente.latitud == null || cliente.longitud == null) return;

    final pendientes = _pedidosPendientes;
    final paradasConCoords = <LatLng>[];

    // 1. Destino inmediato (pedido activo)
    final posActivo = LatLng(cliente.latitud!, cliente.longitud!);
    paradasConCoords.add(posActivo);

    // 2. Destinos posteriores en orden de entrega
    for (final p in pendientes) {
      final c = p.cliente;
      if (c?.latitud != null && c?.longitud != null && c!.latitud != 0 && c.longitud != 0) {
        final pos = LatLng(c.latitud!, c.longitud!);
        if (!paradasConCoords.contains(pos)) {
          paradasConCoords.add(pos);
        }
      }
    }

    _ultimoCalculoRuta = DateTime.now();
    setState(() => _cargandoRuta = true);

    final waypoints = <LatLng>[_posicionMoto!, ...paradasConCoords];

    try {
      final coordsParam = waypoints
          .map((w) => '${w.longitude},${w.latitude}')
          .join(';');

      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/$coordsParam?overview=full&geometries=geojson&steps=true',
      );

      final resp = await http.get(url).timeout(const Duration(seconds: 6));
      if (resp.statusCode == 200) {
        final data = json.decode(resp.body);
        final routes = data['routes'] as List?;
        if (routes != null && routes.isNotEmpty) {
          final legs = routes[0]['legs'] as List?;

          final List<LatLng> puntosActiva = [];
          final List<LatLng> puntosRestante = [];
          double distMetrosActiva = 0.0;

          if (legs != null && legs.isNotEmpty) {
            // Leg 0: De la moto a la primera parada (AZUL)
            distMetrosActiva = (legs[0]['distance'] as num?)?.toDouble() ?? 0.0;
            final leg0Steps = (legs[0]['steps'] as List?) ?? [];
            for (final step in leg0Steps) {
              final stepGeom = step['geometry'];
              if (stepGeom != null && stepGeom['coordinates'] is List) {
                for (final coord in stepGeom['coordinates']) {
                  final pt = LatLng((coord[1] as num).toDouble(), (coord[0] as num).toDouble());
                  if (puntosActiva.isEmpty || puntosActiva.last != pt) {
                    puntosActiva.add(pt);
                  }
                }
              }
            }

            // Legs 1+: Del primer pedido a los demás pedidos (PLOMO)
            for (int i = 1; i < legs.length; i++) {
              final legSteps = (legs[i]['steps'] as List?) ?? [];
              for (final step in legSteps) {
                final stepGeom = step['geometry'];
                if (stepGeom != null && stepGeom['coordinates'] is List) {
                  for (final coord in stepGeom['coordinates']) {
                    final pt = LatLng((coord[1] as num).toDouble(), (coord[0] as num).toDouble());
                    if (puntosRestante.isEmpty || puntosRestante.last != pt) {
                      puntosRestante.add(pt);
                    }
                  }
                }
              }
            }
          }

          if (puntosActiva.isEmpty) {
            final geom = routes[0]['geometry'];
            final coords = geom['coordinates'] as List;
            puntosActiva.addAll(coords.map((c) => LatLng((c[1] as num).toDouble(), (c[0] as num).toDouble())));
          }

          if (mounted) {
            setState(() {
              _puntosRutaActiva = puntosActiva;
              _puntosRutaRestante = puntosRestante;
              _puntosRutaCalle = [...puntosActiva, ...puntosRestante];
              _distanciaKm = distMetrosActiva / 1000.0;
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
        posActivo.latitude,
        posActivo.longitude,
      );
      setState(() {
        _puntosRutaActiva = [_posicionMoto!, posActivo];
        _puntosRutaRestante = paradasConCoords.length > 1 ? paradasConCoords : [];
        _puntosRutaCalle = waypoints;
        _distanciaKm = distMetros / 1000.0;
        _cargandoRuta = false;
      });
    }
  }

  void _centrarEnMoto({double? zoom, double? rotacion}) {
    if (!mounted || _posicionMoto == null) return;
    setState(() {
      _seguirDistribuidor = true;
      _posicionAlPausar = null;
    });
    try {
      double targetZoom = zoom ?? 16.5;
      try {
        final cur = _mapController.camera.zoom;
        if (zoom == null && cur > 12.0) {
          targetZoom = cur;
        }
      } catch (_) {}

      // Rotación del mapa: si está en modo orientado, el mapa rota para que el rumbo quede hacia arriba
      double targetRot = 0.0;
      if (rotacion != null) {
        targetRot = rotacion;
      } else if (_orientarConRumbo && _rumboMoto > 0.0) {
        targetRot = (-_rumboMoto) % 360;
        if (targetRot < 0) targetRot += 360;
      }

      if (_orientarConRumbo && _rumboMoto > 0.0) {
        _mapController.moveAndRotate(_posicionMoto!, targetZoom, targetRot);
      } else {
        _mapController.move(_posicionMoto!, targetZoom);
      }
    } catch (_) {
      try {
        _mapController.move(_posicionMoto!, zoom ?? 16.5);
      } catch (_) {}
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

  Future<void> _enviarWhatsAppLlegadaCliente(Cliente? cliente) async {
    final cel = (cliente?.celular ?? '').trim();
    if (cel.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('⚠️ El cliente no tiene número de teléfono registrado.'),
            backgroundColor: Colors.orange,
          ),
        );
      }
      return;
    }

    String clean = cel.replaceAll(RegExp(r'[^0-9]'), '');
    if (clean.length == 8) {
      clean = '591$clean';
    }

    final mensaje = "👋 Hola, soy el Distribuidor de Agua La Colina.\n\n"
        "🚚 Ya llegué a su ubicación para entregarle su pedido. Por favor, acérquese para recibirlo.\n\n"
        "¡Gracias! 😊";

    final uri = Uri.parse('https://wa.me/$clean?text=${Uri.encodeComponent(mensaje)}');

    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok) {
        final okFallback = await launchUrl(uri, mode: LaunchMode.platformDefault);
        if (!okFallback) {
          throw Exception('No se pudo abrir WhatsApp');
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('No se pudo abrir WhatsApp ($e). Abriendo llamada telefónica...'),
            backgroundColor: Colors.orange,
          ),
        );
      }
      if (cliente != null) {
        await _llamarCliente(cliente.celular);
      }
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
              initialZoom: 16.5,
              initialRotation: (_orientarConRumbo && _rumboMoto > 0.0) ? ((-_rumboMoto) % 360) : 0.0,
              minZoom: 5.0,
              maxZoom: 19.0,
              onPositionChanged: (camera, hasGesture) {
                if (hasGesture) {
                  if (_seguirDistribuidor) {
                    setState(() {
                      _seguirDistribuidor = false;
                      _posicionAlPausar = _posicionMoto;
                    });
                  }
                  _timerAutoReanudarSeguimiento?.cancel();
                  _timerAutoReanudarSeguimiento = Timer(const Duration(seconds: 5), () {
                    if (mounted && !_seguirDistribuidor && _posicionMoto != null) {
                      setState(() {
                        _seguirDistribuidor = true;
                        _posicionAlPausar = null;
                      });
                      _centrarEnMoto();
                    }
                  });
                }
              },
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'com.lacolina.motoquero.motoquero_app',
              ),

              // Trazado de ruta activa (hacia el cliente actual en AZUL) y restantes en PLOMO
              if (_puntosRutaCalle.isNotEmpty)
                PolylineLayer(
                  polylines: [
                    // 1. Tramo restante hacia los pedidos posteriores (PLOMO)
                    if (_puntosRutaRestante.isNotEmpty)
                      Polyline(
                        points: _puntosRutaRestante,
                        strokeWidth: 4.5,
                        color: const Color(0xFF78909C), // Plomo / Gris elegante
                        borderColor: const Color(0xFF455A64),
                        borderStrokeWidth: 1.0,
                      ),

                    // 2. Tramo activo hacia el primer pedido inmediato a entregar (AZUL)
                    if (_puntosRutaActiva.isNotEmpty)
                      Polyline(
                        points: _puntosRutaActiva,
                        strokeWidth: 5.5,
                        color: const Color(0xFF1E88E5), // Azul ruta activa
                        borderColor: const Color(0xFF0D47A1),
                        borderStrokeWidth: 1.5,
                      ),
                  ],
                ),

              // Marcadores en el mapa
              MarkerLayer(
                rotate: true,
                markers: [
                  // Marcador de la MOTO del motoquero con rumbo y orientación
                  if (_posicionMoto != null)
                    _buildMarcadorMoto(),

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
                  heroTag: 'btnOrientacionRuta',
                  backgroundColor: _orientarConRumbo ? const Color(0xFF1E88E5) : Colors.white,
                  foregroundColor: _orientarConRumbo ? Colors.white : const Color(0xFF455A64),
                  onPressed: () {
                    setState(() {
                      _orientarConRumbo = !_orientarConRumbo;
                    });
                    if (!_orientarConRumbo) {
                      try {
                        _mapController.rotate(0.0);
                      } catch (_) {}
                    }
                    _centrarEnMoto();
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        duration: const Duration(milliseconds: 1400),
                        backgroundColor: _orientarConRumbo ? const Color(0xFF1E88E5) : const Color(0xFF455A64),
                        content: Text(
                          _orientarConRumbo
                              ? '🧭 Modo Navegación: Mapa orientado al frente de la moto'
                              : '🧭 Modo Fijo: Norte arriba',
                        ),
                      ),
                    );
                  },
                  tooltip: _orientarConRumbo ? 'Orientado al frente (Toca para Norte arriba)' : 'Fijar orientación al frente',
                  child: const Icon(Icons.explore),
                ),
                const SizedBox(height: 8),
                FloatingActionButton.small(
                  heroTag: 'btnMiMoto',
                  backgroundColor: _seguirDistribuidor ? const Color(0xFF00C853) : Colors.white,
                  foregroundColor: _seguirDistribuidor ? Colors.white : const Color(0xFF0D47A1),
                  onPressed: _centrarEnMoto,
                  tooltip: _seguirDistribuidor ? 'Centrado automático activo' : 'Centrar en mi moto',
                  child: Icon(_seguirDistribuidor ? Icons.gps_fixed : Icons.my_location),
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

  Marker _buildMarcadorMoto() {
    double rotacionMapaActual = 0.0;
    try {
      rotacionMapaActual = _mapController.camera.rotation;
    } catch (_) {}
    final anguloMarcadorMoto = ((_rumboMoto + rotacionMapaActual) % 360) * (math.pi / 180.0);

    return Marker(
      point: _posicionMoto!,
      width: 56,
      height: 56,
      rotate: true,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: const Color(0xFF1E88E5).withValues(alpha: 0.22),
            ),
          ),
          Transform.rotate(
            angle: anguloMarcadorMoto,
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF0D47A1),
                border: Border.all(color: Colors.white, width: 2.5),
                boxShadow: const [
                  BoxShadow(color: Colors.black45, blurRadius: 5, offset: Offset(0, 2)),
                ],
              ),
              child: Icon(
                _rumboMoto > 0.0 ? Icons.navigation : Icons.two_wheeler,
                color: Colors.white,
                size: _rumboMoto > 0.0 ? 22 : 20,
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
                // Botón WhatsApp Avisar Llegada
                IconButton.filledTonal(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFFE8F5E9),
                    foregroundColor: const Color(0xFF2E7D32),
                  ),
                  onPressed: () => _enviarWhatsAppLlegadaCliente(cliente),
                  icon: const Icon(Icons.chat),
                  tooltip: 'Avisar llegada por WhatsApp',
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
