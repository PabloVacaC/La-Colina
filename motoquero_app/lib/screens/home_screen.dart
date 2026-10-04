import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:http/http.dart' as http;

import '../models/user_model.dart';
import '../models/pedido_model.dart';
import '../services/api_service.dart';
import '../services/location_service.dart';
import 'login_screen.dart';

class HomeScreen extends StatefulWidget {
  final UserSession session;

  const HomeScreen({super.key, required this.session});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final ApiService _api = ApiService();
  final LocationService _location = LocationService();
  final ImagePicker _picker = ImagePicker();
  final MapController _mapController = MapController();

  bool _isLoading = true;
  List<Pedido> _asignados = [];
  List<Pedido> _enCamino = [];
  List<Pedido> _entregados = [];

  // Ruta seleccionada actualmente (A, B, C, D)
  String _rutaSeleccionada = 'A';
  String _filtroRutaEntregados = 'TODAS';

  // Rutas expandidas en la pestaña 1
  final Set<String> _rutasExpandidas = {'A'};

  // Estado del mapa y navegación
  LatLng? _posicionMoto;
  StreamSubscription<Position>? _positionStream;
  Pedido? _pedidoActivo;
  List<LatLng> _puntosRutaCalle = [];
  bool _cargandoRuta = false;
  double? _distanciaKm;
  int? _pedidoProximidadAbiertoId;
  DateTime? _ultimoCalculoRuta;

  String? _ultimoFingerprintSecuencia;

  Timer? _refreshTimer;

  static const List<String> _rutasDisponibles = ['A', 'B', 'C', 'D'];

  // Colores distintivos para cada ruta
  static const Map<String, Color> _coloresRuta = {
    'A': Color(0xFF1E88E5), // Azul
    'B': Color(0xFF2E7D32), // Verde
    'C': Color(0xFFE65100), // Naranja
    'D': Color(0xFF6A1B9A), // Morado
  };

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);

    // Iniciar transmisión de GPS en segundo plano para el admin
    _location.startTracking(widget.session.motoquero.id);

    // Escuchar GPS local para el mapa de navegación
    _iniciarGpsLocal();

    _cargarPedidos();

    // Actualizar periódicamente cada 3 segundos para detectar cambios del admin rápidamente en tiempo real
    _refreshTimer = Timer.periodic(const Duration(seconds: 3), (_) => _cargarPedidos(silent: true));
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _positionStream?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  // =========================================================================
  // CARGA DE DATOS Y GESTIÓN DE RUTAS
  // =========================================================================

  Future<void> _cargarPedidos({bool silent = false}) async {
    if (!silent) setState(() => _isLoading = true);

    try {
      final res = await _api.getPedidos(widget.session.motoquero.id);
      if (mounted) {
        setState(() {
          _asignados = res['asignados'] ?? [];
          _enCamino = res['en_camino'] ?? [];
          _entregados = res['entregados'] ?? [];
          _isLoading = false;
        });

        _verificarYActualizarPedidoActivo();
      }
    } catch (_) {
      if (mounted && !silent) {
        setState(() => _isLoading = false);
      }
    }
  }

  /// Agrupa todos los pedidos del motoquero por ruta (A, B, C, D)
  Map<String, List<Pedido>> get _pedidosPorRuta {
    final map = <String, List<Pedido>>{
      'A': [],
      'B': [],
      'C': [],
      'D': [],
    };

    // Deduplicar pedidos por ID para evitar que un mismo pedido aparezca dos veces
    final todos = <Pedido>[];
    final idsVistos = <int>{};
    for (final p in [..._enCamino, ..._asignados, ..._entregados]) {
      if (idsVistos.add(p.id)) {
        todos.add(p);
      }
    }

    for (final p in todos) {
      final r = (p.ruta ?? 'A').toUpperCase().trim();
      if (map.containsKey(r)) {
        map[r]!.add(p);
      } else {
        map['A']!.add(p);
      }
    }

    // Ordenar cada ruta:
    // 1. Entregados al final
    // 2. Por orden ascendente (#1, #2, #3...) asignado por el admin
    // 3. Emergencias si no tienen orden distinto
    map.forEach((_, list) {
      list.sort((a, b) {
        final aEntregado = a.estado == 'Entregado';
        final bEntregado = b.estado == 'Entregado';
        if (aEntregado && !bEntregado) return 1;
        if (!aEntregado && bEntregado) return -1;

        if (a.orden > 0 && b.orden > 0 && a.orden != b.orden) {
          return a.orden.compareTo(b.orden);
        }
        if (a.orden > 0 && b.orden <= 0) return -1;
        if (a.orden <= 0 && b.orden > 0) return 1;

        if (a.emergencia && !b.emergencia) return -1;
        if (!a.emergencia && b.emergencia) return 1;

        return a.id.compareTo(b.id);
      });
    });

    return map;
  }

  /// Pedidos de la ruta actualmente activa
  List<Pedido> get _pedidosRutaActiva {
    return _pedidosPorRuta[_rutaSeleccionada] ?? [];
  }

  /// Solo las rutas que tienen pedidos formalmente asignados por el administrador
  List<String> get _rutasAsignadasDisponibles {
    final porRuta = _pedidosPorRuta;
    return _rutasDisponibles.where((r) => (porRuta[r] ?? []).isNotEmpty).toList();
  }

  /// Pedidos pendientes (no entregados) de la ruta activa ordenados estrictamente por el admin
  List<Pedido> get _pedidosPendientesRutaActiva {
    return _pedidosRutaActiva.where((p) => p.estado != 'Entregado').toList()
      ..sort((a, b) {
        // 1. Respetar estrictamente el orden (#1, #2, #3...) establecido por el administrador
        if (a.orden > 0 && b.orden > 0 && a.orden != b.orden) {
          return a.orden.compareTo(b.orden);
        }
        if (a.orden > 0 && b.orden <= 0) return -1;
        if (a.orden <= 0 && b.orden > 0) return 1;

        // 2. Pedidos de emergencia si no tienen orden distinto
        if (a.emergencia && !b.emergencia) return -1;
        if (!a.emergencia && b.emergencia) return 1;

        // 3. Estado en camino
        if (a.estado == 'En camino' && b.estado != 'En camino') return -1;
        if (b.estado == 'En camino' && a.estado != 'En camino') return 1;

        return a.id.compareTo(b.id);
      });
  }

  void _verificarYActualizarPedidoActivo() {
    final rutasValidas = _rutasAsignadasDisponibles;

    if (rutasValidas.isEmpty) {
      setState(() {
        _pedidoActivo = null;
        _puntosRutaCalle = [];
        _distanciaKm = null;
        _ultimoFingerprintSecuencia = null;
      });
      return;
    }

    // Si la ruta actualmente seleccionada no está entre las asignadas, cambiar a la primera asignada
    if (!rutasValidas.contains(_rutaSeleccionada)) {
      _rutaSeleccionada = rutasValidas.first;
    }

    var pendientes = _pedidosPendientesRutaActiva;

    // Si la ruta actualmente seleccionada no tiene pedidos pendientes de entregar,
    // buscar automáticamente la primera ruta asignada que SÍ tenga entregas pendientes (ej. Ruta C)
    if (pendientes.isEmpty) {
      for (final r in rutasValidas) {
        final pedidosR = _pedidosPorRuta[r] ?? [];
        if (pedidosR.any((p) => p.estado != 'Entregado')) {
          _rutaSeleccionada = r;
          pendientes = _pedidosPendientesRutaActiva;
          break;
        }
      }
    }

    if (pendientes.isEmpty) {
      setState(() {
        _pedidoActivo = null;
        _puntosRutaCalle = [];
        _distanciaKm = null;
        _ultimoFingerprintSecuencia = null;
      });
      return;
    }

    // Huella digital de toda la secuencia de pedidos y sus órdenes
    final fingerprintActual = pendientes.map((p) => '${p.id}:${p.orden}:${p.emergencia}').join('|');

    final adminCambioSecuencia = _ultimoFingerprintSecuencia != null &&
        _ultimoFingerprintSecuencia != fingerprintActual;

    final nuevoPrimero = pendientes.first;

    final requiereNuevoCalculo = _pedidoActivo == null ||
        _pedidoActivo!.estado == 'Entregado' ||
        !pendientes.any((p) => p.id == _pedidoActivo!.id) ||
        adminCambioSecuencia;

    _ultimoFingerprintSecuencia = fingerprintActual;

    if (requiereNuevoCalculo) {
      setState(() {
        _pedidoActivo = nuevoPrimero;
      });
      _calcularRutaCalle(forzar: true);

      // Notificar al motoquero si el administrador reordenó la ruta en tiempo real
      if (adminCambioSecuencia && mounted) {
        HapticFeedback.mediumImpact();
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFFE65100),
            duration: const Duration(seconds: 4),
            content: Row(
              children: [
                const Icon(Icons.alt_route, color: Colors.white),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '¡Orden de ruta actualizado por el administrador!\nSiguiente parada #${nuevoPrimero.orden > 0 ? nuevoPrimero.orden : 1}: ${nuevoPrimero.cliente?.nombre ?? ""}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ),
              ],
            ),
          ),
        );
      }
    } else {
      // Mantener actualizados los datos del pedido activo si no cambió
      final pActualizado = pendientes.firstWhere(
        (p) => p.id == _pedidoActivo!.id,
        orElse: () => nuevoPrimero,
      );
      if (_pedidoActivo != pActualizado) {
        setState(() {
          _pedidoActivo = pActualizado;
        });
      }
    }
  }

  // =========================================================================
  // GPS LOCAL, TRAZADO OSRM Y PROXIMIDAD (20 METROS)
  // =========================================================================

  Future<void> _iniciarGpsLocal() async {
    // 1. Obtener de inmediato la última ubicación conocida (cero espera)
    try {
      final lastPos = await Geolocator.getLastKnownPosition();
      if (lastPos != null && mounted) {
        setState(() {
          _posicionMoto = LatLng(lastPos.latitude, lastPos.longitude);
        });
        _calcularRutaCalle(forzar: true);
      }
    } catch (_) {}

    // 2. Obtener posición GPS actual precisa
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 6),
        ),
      );
      if (mounted) {
        setState(() {
          _posicionMoto = LatLng(pos.latitude, pos.longitude);
        });
        _calcularRutaCalle(forzar: true);
      }
    } catch (_) {}

    // 3. Escuchar flujo de GPS en tiempo real
    _positionStream = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 3, // cada 3 metros para reaccionar de inmediato
      ),
    ).listen((pos) {
      if (!mounted) return;
      final moto = LatLng(pos.latitude, pos.longitude);
      setState(() {
        _posicionMoto = moto;
      });

      _verificarProximidad(moto);
      _evaluarRecalculoRuta(moto);
    });
  }

  /// Evalúa si es necesario recalcular la ruta sobre calles (evita saturar OSRM cada 8 metros)
  void _evaluarRecalculoRuta(LatLng moto) {
    if (_pedidoActivo == null) return;
    final c = _pedidoActivo!.cliente;
    if (c == null || c.latitud == null || c.longitud == null) return;

    // 1. Si no hay ruta trazada aún, calcularla inmediatamente
    if (_puntosRutaCalle.isEmpty) {
      _calcularRutaCalle();
      return;
    }

    final ahora = DateTime.now();
    final segDesdeUltimo = _ultimoCalculoRuta == null
        ? 999
        : ahora.difference(_ultimoCalculoRuta!).inSeconds;

    // Protección: no hacer peticiones web con menos de 15 segundos de diferencia
    if (segDesdeUltimo < 15) return;

    // 2. Verificar si hubo desvío (> 75m del punto más cercano del trazado)
    double distMinima = double.infinity;
    for (final p in _puntosRutaCalle) {
      final d = Geolocator.distanceBetween(moto.latitude, moto.longitude, p.latitude, p.longitude);
      if (d < distMinima) distMinima = d;
      if (distMinima <= 75) break;
    }

    final seDesvio = distMinima > 75;

    // 3. Recalcular si se salió de ruta o si pasaron al menos 60 segundos
    if (seDesvio || segDesdeUltimo >= 60) {
      _calcularRutaCalle();
    }
  }

  /// Verifica si el motoquero está a 20 metros o menos del cliente activo
  void _verificarProximidad(LatLng posMoto) {
    if (_pedidoActivo == null) return;
    if (_pedidoActivo!.estado == 'Entregado') return;
    final c = _pedidoActivo!.cliente;
    if (c == null || c.latitud == null || c.longitud == null) return;

    final distMetros = Geolocator.distanceBetween(
      posMoto.latitude,
      posMoto.longitude,
      c.latitud!,
      c.longitud!,
    );

    // Margen de 25m para imprecisiones GPS (cubriendo perfectamente los 20m)
    if (distMetros <= 25 && _pedidoProximidadAbiertoId != _pedidoActivo!.id) {
      _pedidoProximidadAbiertoId = _pedidoActivo!.id;
      HapticFeedback.heavyImpact();

      // Abrir automáticamente la tarjeta de entrega del cliente
      _mostrarVentanaEntregaCliente(_pedidoActivo!);
    }
  }

  /// Trazado de ruta sobre las calles reales con OSRM conectando paradas en orden
  Future<void> _calcularRutaCalle({bool forzar = false}) async {
    if (_cargandoRuta && !forzar) return;

    final pendientes = _pedidosPendientesRutaActiva;
    if (pendientes.isEmpty) {
      if (mounted) {
        setState(() {
          _puntosRutaCalle = [];
          _distanciaKm = null;
        });
      }
      return;
    }

    // Si aún no se fijó la moto, obtener de inmediato la última posición conocida
    if (_posicionMoto == null) {
      try {
        final last = await Geolocator.getLastKnownPosition();
        if (last != null && mounted) {
          setState(() {
            _posicionMoto = LatLng(last.latitude, last.longitude);
          });
        }
      } catch (_) {}
    }

    if (_posicionMoto == null) return;

    // Construir la lista de paradas en el orden estricto de entrega
    final paradasConCoords = <LatLng>[];

    // Si el usuario seleccionó un pedido activo específico que no es el primero,
    // ese va como destino inmediato:
    if (_pedidoActivo != null && _pedidoActivo != pendientes.first) {
      final cActivo = _pedidoActivo!.cliente;
      if (cActivo?.latitud != null && cActivo?.longitud != null && cActivo!.latitud != 0 && cActivo.longitud != 0) {
        paradasConCoords.add(LatLng(cActivo.latitud!, cActivo.longitud!));
      }
    }

    // Agregar todas las paradas pendientes en el orden de entrega programado
    for (final p in pendientes) {
      final c = p.cliente;
      if (c?.latitud != null && c?.longitud != null && c!.latitud != 0 && c.longitud != 0) {
        final pos = LatLng(c.latitud!, c.longitud!);
        if (!paradasConCoords.contains(pos)) {
          paradasConCoords.add(pos);
        }
      }
    }

    if (paradasConCoords.isEmpty) return;

    _ultimoCalculoRuta = DateTime.now();
    setState(() => _cargandoRuta = true);

    // Coordenadas completas: Moto -> Parada 1 -> Parada 2 -> Parada 3...
    final waypoints = <LatLng>[_posicionMoto!, ...paradasConCoords];

    try {
      final coordsParam = waypoints
          .map((w) => '${w.longitude},${w.latitude}')
          .join(';');

      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/$coordsParam?overview=full&geometries=geojson',
      );

      final resp = await http.get(url).timeout(const Duration(seconds: 5));
      if (resp.statusCode == 200) {
        final data = json.decode(resp.body);
        final routes = data['routes'] as List?;
        if (routes != null && routes.isNotEmpty) {
          final geom = routes[0]['geometry'];
          final coords = geom['coordinates'] as List;

          // Distancia de la primera pierna (de la moto a la siguiente parada)
          double distMetros = 0.0;
          final legs = routes[0]['legs'] as List?;
          if (legs != null && legs.isNotEmpty) {
            distMetros = (legs[0]['distance'] as num?)?.toDouble() ?? 0.0;
          } else {
            distMetros = (routes[0]['distance'] as num?)?.toDouble() ?? 0.0;
          }

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
    } catch (_) {}

    // Fallback si OSRM no responde o falla la red: unir puntos secuencialmente
    if (mounted) {
      final primerDestino = paradasConCoords.first;
      final distMetros = Geolocator.distanceBetween(
        _posicionMoto!.latitude,
        _posicionMoto!.longitude,
        primerDestino.latitude,
        primerDestino.longitude,
      );
      setState(() {
        _puntosRutaCalle = waypoints;
        _distanciaKm = distMetros / 1000.0;
        _cargandoRuta = false;
      });
    }
  }

  // =========================================================================
  // ACCIONES DE RUTA Y ENTREGA
  // =========================================================================

  Future<void> _empezarRuta(String ruta) async {
    setState(() {
      _rutaSeleccionada = ruta;
      _pedidoProximidadAbiertoId = null;
    });

    // Cambiar a la pestaña "En Camino" (pestaña 1 - Mapa)
    _tabController.animateTo(1);

    final pendientes = _pedidosPendientesRutaActiva;
    if (pendientes.isNotEmpty) {
      setState(() {
        _pedidoActivo = pendientes.first;
      });
      _calcularRutaCalle(forzar: true);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFF00C853),
          content: Text('¡Ruta $ruta iniciada! Sigue el mapa de entrega.'),
        ),
      );
    }
  }

  Future<void> _finalizarPedidoYContinuar(
    Pedido pedido,
    String metodoPago, {
    List<Map<String, dynamic>>? items,
  }) async {
    // Si ya está entregado, bloquear cualquier modificación
    if (pedido.estado == 'Entregado') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Colors.orange,
          content: Text('Este pedido ya fue entregado y no puede modificarse.'),
        ),
      );
      return;
    }

    final success = await _api.finalizarPedido(pedido.id, metodoPago, items: items);
    if (!mounted) return;

    if (!success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo guardar la entrega. Revisa tu conexión.')),
      );
      return;
    }

    // Actualizar de inmediato el estado local del pedido
    setState(() {
      pedido.estado = 'Entregado';
      pedido.metodoPago = metodoPago;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF00C853),
        content: Text('¡Pedido #${pedido.id} entregado con éxito!'),
      ),
    );

    // Actualizar pedidos
    await _cargarPedidos(silent: true);

    // Buscar el siguiente pedido de esta ruta
    final pendientesRestantes = _pedidosPendientesRutaActiva;

    if (pendientesRestantes.isNotEmpty) {
      setState(() {
        _pedidoActivo = pendientesRestantes.first;
        _pedidoProximidadAbiertoId = null;
      });
      _calcularRutaCalle(forzar: true);

      if (_posicionMoto != null && _pedidoActivo?.cliente?.latitud != null) {
        _mapController.move(
          LatLng(_pedidoActivo!.cliente!.latitud!, _pedidoActivo!.cliente!.longitud!),
          15.5,
        );
      }
    } else {
      // 🎉 ¡Ruta completada!
      setState(() {
        _pedidoActivo = null;
        _puntosRutaCalle = [];
      });

      _mostrarDialogoRutaCompletada(_rutaSeleccionada);
    }
  }

  void _mostrarDialogoRutaCompletada(String ruta) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.stars, color: Colors.amber, size: 28),
            SizedBox(width: 8),
            Text('¡Ruta Completada!'),
          ],
        ),
        content: Text(
          'Has completado todos los pedidos de la Ruta $ruta con éxito.\n\n'
          'Ahora puedes seleccionar otra ruta para continuar distribuyendo.',
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF00C853),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () {
              Navigator.pop(ctx);
              _tabController.animateTo(0); // Volver a pestaña Rutas
            },
            child: const Text('SELECCIONAR OTRA RUTA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  // =========================================================================
  // CANCELACIÓN DE ENTREGA Y CONTINUACIÓN A LA SIGUIENTE PARADA
  // =========================================================================

  Future<void> _confirmarCancelarEntrega(Pedido pedido) async {
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
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '¿Deseas cancelar la entrega del Pedido #${pedido.id}?',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
            ),
            const SizedBox(height: 8),
            Text(
              'Cliente: ${pedido.cliente?.nombre ?? "Desconocido"}',
              style: const TextStyle(color: Colors.black87),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF3E0),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.orange.shade200),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, size: 18, color: Color(0xFFE65100)),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'El pedido se retirará de tu ruta (ej: cliente no salió o no atiende) y pasarás automáticamente a la siguiente ubicación.',
                      style: TextStyle(fontSize: 12, color: Color(0xFFE65100)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
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

    if (confirm == true) {
      await _cancelarPedidoYContinuar(pedido);
    }
  }

  Future<void> _cancelarPedidoYContinuar(Pedido pedido) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(
        child: CircularProgressIndicator(color: Color(0xFFD32F2F)),
      ),
    );

    final success = await _api.cancelarPedido(pedido.id, motivo: 'Cliente no sale / no atiende');

    if (mounted) {
      Navigator.of(context, rootNavigator: true).pop(); // Cerrar loader
    }

    if (!mounted) return;

    if (!success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Colors.red,
          content: Text('No se pudo cancelar el pedido. Verifica tu conexión a internet.'),
        ),
      );
      return;
    }

    // 1. Quitar de las listas locales de inmediato
    setState(() {
      _asignados.removeWhere((p) => p.id == pedido.id);
      _enCamino.removeWhere((p) => p.id == pedido.id);
      if (_pedidoActivo?.id == pedido.id) {
        _pedidoActivo = null;
        _puntosRutaCalle = [];
        _distanciaKm = null;
        _pedidoProximidadAbiertoId = null;
      }
    });

    HapticFeedback.mediumImpact();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFFD32F2F),
        duration: const Duration(seconds: 4),
        content: Row(
          children: [
            const Icon(Icons.cancel, color: Colors.white),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Entrega #${pedido.id} cancelada. Pasando a la siguiente ubicación...',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
            ),
          ],
        ),
      ),
    );

    // 2. Refrescar datos desde el servidor
    await _cargarPedidos(silent: true);

    // 3. Buscar la siguiente parada pendiente de esta ruta
    final pendientesRestantes = _pedidosPendientesRutaActiva;

    if (pendientesRestantes.isNotEmpty) {
      setState(() {
        _pedidoActivo = pendientesRestantes.first;
        _pedidoProximidadAbiertoId = null;
      });
      _calcularRutaCalle(forzar: true);

      if (_posicionMoto != null && _pedidoActivo?.cliente?.latitud != null) {
        _mapController.move(
          LatLng(_pedidoActivo!.cliente!.latitud!, _pedidoActivo!.cliente!.longitud!),
          15.5,
        );
      }
    } else {
      // Si la ruta activa quedó sin pedidos pendientes, verificar otras rutas o finalizar
      _verificarYActualizarPedidoActivo();

      if (_pedidoActivo == null) {
        _mostrarDialogoRutaCompletada(_rutaSeleccionada);
      }
    }
  }

  // =========================================================================
  // VENTANA EXACTA DE ENTREGA (CAPTURA DEL USUARIO)
  // =========================================================================

  void _mostrarVentanaEntregaCliente(Pedido pedido) {
    final c = pedido.cliente;
    final esEntregado = pedido.estado == 'Entregado';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              margin: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: esEntregado ? Colors.green.shade600 : const Color(0xFF00C853),
                  width: 2,
                ),
                boxShadow: const [
                  BoxShadow(color: Colors.black26, blurRadius: 16, offset: Offset(0, 4)),
                ],
              ),
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Barra superior: Badge Pedido y Precio
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Flexible(
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: esEntregado ? const Color(0xFFE8F5E9) : const Color(0xFFFFF3E0),
                                borderRadius: BorderRadius.circular(8),
                                border: esEntregado ? Border.all(color: Colors.green.shade300) : null,
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (esEntregado) ...[
                                    const Icon(Icons.check_circle, size: 14, color: Color(0xFF00C853)),
                                    const SizedBox(width: 4),
                                  ],
                                  Flexible(
                                    child: Text(
                                      esEntregado
                                          ? 'Parada ${pedido.orden > 0 ? pedido.orden : 1} • Entregado'
                                          : 'Parada ${pedido.orden > 0 ? pedido.orden : 1} • En Camino',
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        color: esEntregado ? const Color(0xFF2E7D32) : const Color(0xFFEF6C00),
                                        fontSize: 13,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            'Bs. ${pedido.totalPrecio.toStringAsFixed(2)}',
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF00C853),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Nombre del cliente
                      Text(
                        c?.nombre ?? 'Cliente',
                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.black87),
                      ),
                      const SizedBox(height: 6),

                      // Ubicación / Detalle de botellones
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.location_on, size: 18, color: Colors.red),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              c?.direccion ?? 'Sin dirección',
                              style: const TextStyle(fontSize: 13, color: Colors.black87),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),

                      // Fila de 2 botones: MAPA GPS y LLAMAR
                      Row(
                        children: [
                          Expanded(
                            child: ElevatedButton.icon(
                              onPressed: () => _mostrarUbicacionEnGoogleMaps(c),
                              icon: const Icon(Icons.map, size: 16, color: Colors.white),
                              label: const Text(
                                'MAPA GPS',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF0D47A1), // Azul marino
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: ElevatedButton.icon(
                              onPressed: () => _llamarCliente(c?.celular),
                              icon: const Icon(Icons.phone, size: 16, color: Colors.white),
                              label: const Text(
                                'LLAMAR',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF00897B), // Verde azulado / teal
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),

                      // Recuadro: Foto de Referencia / Casa
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8F9FA),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              '📷 Foto de Referencia / Casa:',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.black87),
                            ),
                            const SizedBox(height: 8),

                            if (c?.imagenCasaUrl != null && c!.imagenCasaUrl!.isNotEmpty)
                              ClipRRect(
                                borderRadius: BorderRadius.circular(8),
                                child: Image.network(
                                  c.imagenCasaUrl!,
                                  height: 140,
                                  width: double.infinity,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, error) => Container(
                                    height: 70,
                                    color: Colors.grey.shade200,
                                    alignment: Alignment.center,
                                    child: const Text('Error al cargar imagen previa', style: TextStyle(fontSize: 12, color: Colors.grey)),
                                  ),
                                ),
                              )
                            else
                              Container(
                                height: 70,
                                width: double.infinity,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: Colors.grey.shade200,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Text(
                                  'Sin foto previa de la casa.',
                                  style: TextStyle(color: Colors.grey, fontSize: 12),
                                ),
                              ),

                            const SizedBox(height: 8),

                            // Botón Cambiar / Tomar Foto (solo si no ha sido entregado)
                            if (!esEntregado) ...[
                              const SizedBox(height: 8),
                              SizedBox(
                                width: double.infinity,
                                child: OutlinedButton.icon(
                                  onPressed: c != null
                                      ? () async {
                                          await _tomarYSubirFoto(c);
                                          setModalState(() {});
                                        }
                                      : null,
                                  icon: const Icon(Icons.camera_alt, size: 16),
                                  label: Text(c?.imagenCasaUrl != null ? 'Cambiar Foto' : 'Tomar / Subir Foto'),
                                  style: OutlinedButton.styleFrom(
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Si ya fue entregado: mostrar resumen informativo del pago
                      if (esEntregado) ...[
                        Container(
                          width: double.infinity,
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                          decoration: BoxDecoration(
                            color: Colors.green.shade50,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: Colors.green.shade200),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    (pedido.metodoPago ?? '').toLowerCase().contains('qr')
                                        ? Icons.qr_code
                                        : Icons.payments_outlined,
                                    size: 18,
                                    color: Colors.green.shade800,
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    'Cobrado con: ${pedido.metodoPago ?? "Efectivo"}',
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 13,
                                      color: Colors.green.shade800,
                                    ),
                                  ),
                                ],
                              ),
                              Text(
                                'Bs. ${pedido.totalPrecio.toStringAsFixed(2)}',
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 15,
                                  color: Colors.green.shade800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],

                      // Botones de acción: COMPLETAR ENTREGA y CANCELAR ENTREGA (solo disponible si no ha sido entregado)
                      if (!esEntregado) ...[
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: () {
                              Navigator.pop(ctx);
                              WidgetsBinding.instance.addPostFrameCallback((_) {
                                if (mounted) {
                                  _mostrarDialogoMetodoPago(pedido);
                                }
                              });
                            },
                            icon: const Icon(Icons.check_circle, size: 20, color: Colors.white),
                            label: const Text(
                              'COMPLETAR ENTREGA',
                              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF00C853), // Verde brillante
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                        const SizedBox(height: 10),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: () {
                              Navigator.pop(ctx);
                              WidgetsBinding.instance.addPostFrameCallback((_) {
                                if (mounted) {
                                  _confirmarCancelarEntrega(pedido);
                                }
                              });
                            },
                            icon: const Icon(Icons.cancel_outlined, size: 20, color: Color(0xFFD32F2F)),
                            label: const Text(
                              'CANCELAR ENTREGA',
                              style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFFD32F2F)),
                            ),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Color(0xFFD32F2F), width: 1.5),
                              backgroundColor: const Color(0xFFFFEBEE),
                              padding: const EdgeInsets.symmetric(vertical: 13),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                      ]
                      else
                        // Mensaje de solo lectura: el distribuidor ya no puede modificar el pedido
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.green.shade400, width: 1.5),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: const [
                              Icon(Icons.check_circle, color: Color(0xFF00C853), size: 24),
                              SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  'Pedido entregado y finalizado.\nNo se puede volver a modificar.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: Color(0xFF2E7D32),
                                    fontSize: 13,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  /// Diálogo con desglose de productos y método de pago al completar entrega (exacto al sistema web)
  Future<void> _mostrarDialogoMetodoPago(Pedido pedido) async {
    // Si ya está entregado, no permitir abrir el modal ni modificar
    if (pedido.estado == 'Entregado') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Colors.orange,
          content: Text('Este pedido ya fue entregado y no se puede modificar.'),
        ),
      );
      return;
    }

    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => _DialogoFinalizarEntrega(
        pedido: pedido,
        api: _api,
        pedidosEntregados: _entregados,
      ),
    );

    if (result != null) {
      final metodoPago = result['metodo_pago'] as String;
      final items = result['items'] as List<Map<String, dynamic>>?;
      await _finalizarPedidoYContinuar(pedido, metodoPago, items: items);
    }
  }

  Future<void> _tomarYSubirFoto(Cliente cliente) async {
    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (ctx) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Color(0xFF0D47A1)),
              title: const Text('Tomar foto con la Cámara'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: Color(0xFF0D47A1)),
              title: const Text('Seleccionar de la Galería'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (source == null) return;

    try {
      final XFile? photo = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 85,
      );

      if (photo == null) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Subiendo foto al servidor...')),
      );

      final nuevaUrl = await _api.subirFotoCasa(cliente.id, photo);
      if (nuevaUrl != null && mounted) {
        setState(() {
          cliente.imagenCasaUrl = nuevaUrl;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Color(0xFF00C853),
            content: Text('¡Foto guardada correctamente!'),
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error al subir foto: $e')),
      );
    }
  }

  Future<void> _llamarCliente(String? celular) async {
    if (celular == null || celular.isEmpty) return;
    final clean = celular.replaceAll(RegExp(r'[^0-9+]'), '');
    final url = Uri.parse('tel:$clean');
    if (await canLaunchUrl(url)) {
      await launchUrl(url);
    }
  }

  Future<void> _abrirNavegacionExterna(Cliente? cliente) async {
    if (cliente?.latitud == null || cliente?.longitud == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Este cliente no tiene coordenadas GPS registradas.'),
            backgroundColor: Colors.orange,
          ),
        );
      }
      return;
    }
    final lat = cliente!.latitud!;
    final lng = cliente.longitud!;

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

  /// Abre Google Maps mostrando únicamente el marcador/ubicación del cliente en el mapa
  /// sin iniciar la ruta de navegación automática ("solo mostrar").
  Future<void> _mostrarUbicacionEnGoogleMaps(Cliente? cliente) async {
    if (cliente?.latitud == null || cliente?.longitud == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Este cliente no tiene coordenadas GPS registradas.'),
            backgroundColor: Colors.orange,
          ),
        );
      }
      return;
    }
    final lat = cliente!.latitud!;
    final lng = cliente.longitud!;
    final nombre = Uri.encodeComponent(cliente.nombre.isNotEmpty ? cliente.nombre : 'Cliente');

    // geo:0,0?q=lat,lng(label) muestra el pin directamente en Google Maps sin iniciar navegación
    final geoUrl = Uri.parse('geo:0,0?q=$lat,$lng($nombre)');
    final webUrl = Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng');

    try {
      if (await canLaunchUrl(geoUrl)) {
        await launchUrl(geoUrl, mode: LaunchMode.externalApplication);
      } else {
        await launchUrl(webUrl, mode: LaunchMode.externalApplication);
      }
    } catch (_) {
      try {
        await launchUrl(webUrl, mode: LaunchMode.externalApplication);
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('No se pudo abrir Google Maps: $e')),
          );
        }
      }
    }
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Deseas salir de la aplicación? El GPS se detendrá.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Salir', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      _location.stopTracking();
      await _api.logout();
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
      }
    }
  }

  // =========================================================================
  // INTERFAZ DE USUARIO PRINCIPAL
  // =========================================================================

  @override
  Widget build(BuildContext context) {
    const primaryColor = Color(0xFF0D47A1);

    // Contadores para badges de tabs
    final totalPendientes = _asignados.length + _enCamino.length;

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6F9),
      appBar: AppBar(
        backgroundColor: primaryColor,
        elevation: 2,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.session.motoquero.nombreCompleto,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
            ),
            Text(
              'Moto: ${widget.session.motoquero.placa} • Cel: ${widget.session.motoquero.celular}',
              style: const TextStyle(fontSize: 12, color: Colors.white70),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white),
            tooltip: 'Actualizar',
            onPressed: () => _cargarPedidos(),
          ),
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.white),
            tooltip: 'Cerrar Sesión',
            onPressed: _logout,
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Container(
            color: Colors.white,
            child: TabBar(
              controller: _tabController,
              labelColor: primaryColor,
              unselectedLabelColor: Colors.grey.shade600,
              indicatorColor: primaryColor,
              indicatorWeight: 3,
              labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              tabs: [
                // Pestaña 1: Rutas
                Tab(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.alt_route, size: 18),
                        const SizedBox(width: 4),
                        const Text('Rutas'),
                        if (totalPendientes > 0) ...[
                          const SizedBox(width: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.blueAccent,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '$totalPendientes',
                              style: const TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),

                // Pestaña 2: En Camino (Mapa)
                Tab(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.two_wheeler, size: 18),
                        const SizedBox(width: 4),
                        Text('En Camino ($_rutaSeleccionada)'),
                        if (_pedidosPendientesRutaActiva.isNotEmpty) ...[
                          const SizedBox(width: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFF00C853),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '${_pedidosPendientesRutaActiva.length}',
                              style: const TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),

                // Pestaña 3: Entregados
                Tab(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.assignment_turned_in, size: 18),
                        const SizedBox(width: 4),
                        const Text('Entregados'),
                        if (_entregados.isNotEmpty) ...[
                          const SizedBox(width: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.blueGrey,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '${_entregados.length}',
                              style: const TextStyle(fontSize: 10, color: Colors.white, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          // Banner de transmisión GPS en vivo para el Administrador
          ValueListenableBuilder<LocationTrackingState>(
            valueListenable: _location.stateNotifier,
            builder: (context, state, _) {
              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                color: state.isTracking
                    ? (state.hasError ? Colors.amber.shade100 : Colors.green.shade50)
                    : Colors.grey.shade200,
                child: Row(
                  children: [
                    Container(
                      width: 9,
                      height: 9,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: state.isTracking
                            ? (state.hasError ? Colors.amber.shade800 : Colors.green.shade600)
                            : Colors.grey,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        state.statusMessage,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: state.isTracking
                              ? (state.hasError ? Colors.amber.shade900 : Colors.green.shade900)
                              : Colors.black54,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Switch(
                      value: state.isTracking,
                      activeColor: const Color(0xFF00C853),
                      activeTrackColor: Colors.green.shade200,
                      onChanged: (val) {
                        if (val) {
                          _location.startTracking(widget.session.motoquero.id);
                        } else {
                          _location.stopTracking();
                        }
                      },
                    ),
                  ],
                ),
              );
            },
          ),

          // Pestañas (con physics NeverScrollableScrollPhysics para no interferir con el mapa)
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : TabBarView(
                    controller: _tabController,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _buildTabRutas(),
                      _buildTabEnCaminoMapa(),
                      _buildTabEntregados(),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  // =========================================================================
  // PESTAÑA 1: "RUTAS" (Reemplaza a Asignados)
  // =========================================================================

  Widget _buildTabRutas() {
    final porRuta = _pedidosPorRuta;
    final rutasVisibles = _rutasAsignadasDisponibles;

    if (rutasVisibles.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _cargarPedidos(),
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const SizedBox(height: 70),
            Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      color: Colors.blue.shade50,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(Icons.assignment_outlined, size: 60, color: Colors.blue.shade700),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Sin rutas asignadas',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 20),
                    child: Text(
                      'No tienes pedidos asignados en este momento.\n'
                      'En cuanto el administrador asigne pedidos a tu ruta, aparecerán aquí automáticamente en tiempo real.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey, fontSize: 13, height: 1.4),
                    ),
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton.icon(
                    onPressed: () => _cargarPedidos(),
                    icon: const Icon(Icons.refresh, color: Colors.white),
                    label: const Text('ACTUALIZAR', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0D47A1),
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _cargarPedidos(),
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.blue.shade50,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.blue.shade200),
            ),
            child: Row(
              children: const [
                Icon(Icons.info_outline, color: Color(0xFF0D47A1), size: 22),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Rutas asignadas por el administrador para distribución hoy.',
                    style: TextStyle(fontSize: 13, color: Color(0xFF0D47A1), fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Solo mostrar las rutas asignadas por el administrador
          for (final ruta in rutasVisibles)
            _buildTarjetaRuta(ruta, porRuta[ruta] ?? []),
        ],
      ),
    );
  }

  Widget _buildTarjetaRuta(String ruta, List<Pedido> pedidos) {
    final colorRuta = _coloresRuta[ruta] ?? const Color(0xFF1E88E5);
    final pendientes = pedidos.where((p) => p.estado != 'Entregado').toList();
    final entregados = pedidos.where((p) => p.estado == 'Entregado').toList();
    final enCamino = pedidos.where((p) => p.estado == 'En camino').toList();

    final totalMonto = pedidos.fold<double>(0.0, (s, p) => s + p.totalPrecio);
    final isExpandida = _rutasExpandidas.contains(ruta);
    final esRutaSeleccionada = _rutaSeleccionada == ruta;

    return Card(
      elevation: esRutaSeleccionada ? 4 : 2,
      margin: const EdgeInsets.only(bottom: 14),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: esRutaSeleccionada ? colorRuta : Colors.grey.shade300,
          width: esRutaSeleccionada ? 2.2 : 1.0,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () {
          setState(() {
            if (isExpandida) {
              _rutasExpandidas.remove(ruta);
            } else {
              _rutasExpandidas.add(ruta);
            }
          });
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Cabecera de la Ruta
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: colorRuta,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      ruta,
                      style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              'Ruta $ruta',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: colorRuta),
                            ),
                            if (esRutaSeleccionada) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.green.shade50,
                                  borderRadius: BorderRadius.circular(6),
                                  border: Border.all(color: Colors.green),
                                ),
                                child: const Text(
                                  'ACTIVA',
                                  style: TextStyle(color: Colors.green, fontSize: 10, fontWeight: FontWeight.bold),
                                ),
                              ),
                            ],
                          ],
                        ),
                        Text(
                          '${pedidos.length} pedidos en total • Bs. ${totalMonto.toStringAsFixed(2)}',
                          style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                        ),
                      ],
                    ),
                  ),
                  Icon(
                    isExpandida ? Icons.keyboard_arrow_up : Icons.keyboard_arrow_down,
                    color: Colors.grey,
                  ),
                ],
              ),
              const Divider(height: 20),

              // Métricas rápidas de la ruta
              Row(
                children: [
                  _buildMetricPill(
                    icon: Icons.hourglass_top,
                    label: '${pendientes.length} pendientes',
                    color: pendientes.isNotEmpty ? Colors.orange : Colors.grey,
                  ),
                  const SizedBox(width: 8),
                  _buildMetricPill(
                    icon: Icons.two_wheeler,
                    label: '${enCamino.length} en camino',
                    color: enCamino.isNotEmpty ? const Color(0xFF00C853) : Colors.grey,
                  ),
                  const SizedBox(width: 8),
                  _buildMetricPill(
                    icon: Icons.check_circle,
                    label: '${entregados.length} entregados',
                    color: entregados.isNotEmpty ? Colors.blue : Colors.grey,
                  ),
                ],
              ),

              // Lista expandible de pedidos con su orden (#1, #2, #3...)
              if (isExpandida) ...[
                const SizedBox(height: 12),
                if (pedidos.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Center(
                      child: Text('Sin pedidos registrados en esta ruta.', style: TextStyle(color: Colors.grey, fontSize: 13)),
                    ),
                  )
                else
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Pedidos en el orden de entrega programado:',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.black54),
                      ),
                      const SizedBox(height: 8),
                      for (int idx = 0; idx < pedidos.length; idx++)
                        _buildItemPedidoEnRuta(pedidos[idx], idx + 1),
                    ],
                  ),
              ],

              const SizedBox(height: 14),

              // Botón principal de la Ruta
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: pedidos.isNotEmpty ? () => _empezarRuta(ruta) : null,
                  icon: Icon(
                    enCamino.isNotEmpty ? Icons.navigation : Icons.play_arrow,
                    color: Colors.white,
                  ),
                  label: Text(
                    enCamino.isNotEmpty
                        ? 'CONTINUAR RUTA $ruta EN EL MAPA'
                        : (pendientes.isNotEmpty ? 'EMPEZAR RUTA $ruta' : 'VER RUTA $ruta EN EL MAPA'),
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 14),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: colorRuta,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMetricPill({required IconData icon, required String label, required Color color}) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 13, color: color),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                label,
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: color),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildItemPedidoEnRuta(Pedido p, int posicionRelativa) {
    final c = p.cliente;
    final esEntregado = p.estado == 'Entregado';
    final esEnCamino = p.estado == 'En camino';

    Color badgeColor = Colors.orange;
    if (esEntregado) badgeColor = Colors.green;
    if (esEnCamino) badgeColor = const Color(0xFF00C853);

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: esEntregado ? Colors.green.shade50 : Colors.grey.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: esEntregado ? Colors.green.shade200 : Colors.grey.shade300),
      ),
      child: Row(
        children: [
          // Número de orden (#1, #2, #3...)
          CircleAvatar(
            radius: 12,
            backgroundColor: badgeColor,
            child: Text(
              '${p.orden > 0 ? p.orden : posicionRelativa}',
              style: const TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        c?.nombre ?? 'Cliente',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      '• Parada ${p.orden > 0 ? p.orden : posicionRelativa}',
                      style: TextStyle(fontSize: 11, color: Colors.grey.shade700, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
                Text(
                  c?.direccion ?? 'Sin dirección',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade700),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          Text(
            'Bs. ${p.totalPrecio.toStringAsFixed(2)}',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: esEntregado ? Colors.green : Colors.black87,
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================================
  // PESTAÑA 2: "EN CAMINO" (Mapa con Navegación GPS por calles)
  // =========================================================================

  Widget _buildTabEnCaminoMapa() {
    final rutasDisponibles = _rutasAsignadasDisponibles;

    if (rutasDisponibles.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.map_outlined, size: 70, color: Colors.grey.shade400),
              const SizedBox(height: 16),
              const Text(
                'Sin rutas asignadas para reparto',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              const Text(
                'El mapa se activará automáticamente cuando el administrador te asigne una ruta de entrega.',
                style: TextStyle(color: Colors.grey, fontSize: 13),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: () => _cargarPedidos(),
                icon: const Icon(Icons.refresh, color: Colors.white),
                label: const Text('ACTUALIZAR ESTADO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0D47A1),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final pedidosRuta = _pedidosRutaActiva;
    final colorRuta = _coloresRuta[_rutaSeleccionada] ?? const Color(0xFF1E88E5);

    // Coordenadas para marcadores en el mapa
    final markers = <Marker>[];

    // 1. Marcador de la MOTO del distribuidor
    if (_posicionMoto != null) {
      markers.add(
        Marker(
          point: _posicionMoto!,
          width: 50,
          height: 50,
          child: Stack(
            alignment: Alignment.center,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.blue.withOpacity(0.25),
                ),
              ),
              Container(
                width: 34,
                height: 34,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFF0D47A1),
                  boxShadow: [BoxShadow(color: Colors.black38, blurRadius: 6)],
                ),
                child: const Icon(Icons.two_wheeler, color: Colors.white, size: 20),
              ),
            ],
          ),
        ),
      );
    }

    // 2. Marcadores de los pedidos de la ruta activa con sus números (#1, #2, #3...)
    final pendientes = _pedidosPendientesRutaActiva;

    for (int i = 0; i < pedidosRuta.length; i++) {
      final p = pedidosRuta[i];
      final c = p.cliente;
      if (c?.latitud == null || c?.longitud == null) continue;

      final pos = LatLng(c!.latitud!, c.longitud!);
      final esActivo = _pedidoActivo?.id == p.id;
      final esEntregado = p.estado == 'Entregado';

      // Asignar número único consecutivo que coincida con el orden de paradas de esta ruta (#1, #2, #3...)
      int numBadge = 0;
      if (!esEntregado) {
        final idxPendiente = pendientes.indexWhere((item) => item.id == p.id);
        numBadge = p.orden > 0 ? p.orden : (idxPendiente >= 0 ? (idxPendiente + 1) : (i + 1));
      }

      Color markerColor = colorRuta;
      if (esEntregado) {
        markerColor = Colors.grey;
      } else if (esActivo) {
        markerColor = const Color(0xFF00C853); // Verde brillante
      }

      markers.add(
        Marker(
          point: pos,
          width: esActivo ? 50 : 38,
          height: esActivo ? 50 : 38,
          child: GestureDetector(
            onTap: () {
              if (esEntregado) {
                // Si ya fue entregado, abrir solo para consulta informativa (solo lectura)
                // NO se fija como destino activo ni se recalcula ruta
                _mostrarVentanaEntregaCliente(p);
                return;
              }
              setState(() {
                _pedidoActivo = p;
                _pedidoProximidadAbiertoId = null;
              });
              _calcularRutaCalle(forzar: true);
              _mostrarVentanaEntregaCliente(p);
            },
            child: Stack(
              alignment: Alignment.center,
              children: [
                if (esActivo)
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF00C853).withOpacity(0.3),
                    ),
                  ),
                Container(
                  width: esActivo ? 36 : 28,
                  height: esActivo ? 36 : 28,
                  decoration: BoxDecoration(
                    color: markerColor,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                    boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 4)],
                  ),
                  alignment: Alignment.center,
                  child: esEntregado
                      ? const Icon(Icons.check, color: Colors.white, size: 16)
                      : Text(
                          '$numBadge',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: esActivo ? 15 : 12,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Stack(
      children: [
        // Mapa Leaflet / OpenStreetMap
        FlutterMap(
          mapController: _mapController,
          options: MapOptions(
            initialCenter: _posicionMoto ?? const LatLng(-17.7833, -63.1821),
            initialZoom: 14.5,
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.lacolina.motoquero_app',
            ),
            if (_puntosRutaCalle.isNotEmpty)
              PolylineLayer(
                polylines: [
                  Polyline(
                    points: _puntosRutaCalle,
                    strokeWidth: 5.0,
                    color: const Color(0xFF1E88E5), // Azul ruta calle
                  ),
                ],
              ),
            MarkerLayer(markers: markers),
          ],
        ),

        // Barra superior flotante: Selector de Ruta y progreso
        Positioned(
          top: 10,
          left: 10,
          right: 10,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, 2))],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_cargandoRuta)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 6),
                    child: LinearProgressIndicator(minHeight: 2),
                  ),
                Row(
                  children: [
                // Selector de Ruta
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: colorRuta.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: colorRuta),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      value: rutasDisponibles.contains(_rutaSeleccionada)
                          ? _rutaSeleccionada
                          : (rutasDisponibles.isNotEmpty ? rutasDisponibles.first : null),
                      isDense: true,
                      icon: Icon(Icons.arrow_drop_down, color: colorRuta),
                      style: TextStyle(fontWeight: FontWeight.bold, color: colorRuta, fontSize: 13),
                      items: rutasDisponibles.map((r) {
                        return DropdownMenuItem(
                          value: r,
                          child: Text('Ruta $r'),
                        );
                      }).toList(),
                      onChanged: (nuevaRuta) {
                        if (nuevaRuta != null) _empezarRuta(nuevaRuta);
                      },
                    ),
                  ),
                ),
                const SizedBox(width: 10),

                // Progreso
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Progreso: ${_pedidosRutaActiva.where((p) => p.estado == 'Entregado').length}/${_pedidosRutaActiva.length} entregados',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                      ),
                      if (_distanciaKm != null && _pedidoActivo != null)
                        Text(
                          _distanciaKm! < 1.0
                              ? 'A ${(_distanciaKm! * 1000).round()} m de ${_pedidoActivo?.cliente?.nombre ?? 'Cliente'}'
                              : 'A ${_distanciaKm!.toStringAsFixed(1)} km del cliente',
                          style: TextStyle(fontSize: 11, color: Colors.blue.shade900, fontWeight: FontWeight.w600),
                        ),
                    ],
                  ),
                ),

                // Botón Centrar en Moto
                IconButton(
                  icon: const Icon(Icons.my_location, color: Color(0xFF0D47A1)),
                  tooltip: 'Centrar en mi ubicación',
                  onPressed: () {
                    if (_posicionMoto != null) {
                      _mapController.move(_posicionMoto!, 16.0);
                    }
                  },
                ),
              ],
            ),
            ],
          ),
          ),
        ),

        // Tarjeta flotante inferior con el pedido activo (solo si sigue pendiente)
        if (_pedidoActivo != null && _pedidoActivo!.estado != 'Entregado')
          Positioned(
            bottom: 12,
            left: 12,
            right: 12,
            child: _buildTarjetaFlotantePedidoActivo(_pedidoActivo!),
          ),
      ],
    );
  }

  Widget _buildTarjetaFlotantePedidoActivo(Pedido p) {
    if (p.estado == 'Entregado') return const SizedBox.shrink();
    final c = p.cliente;

    return Card(
      elevation: 6,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFF00C853), width: 1.8),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: p.emergencia ? const Color(0xFFFFEBEE) : const Color(0xFFFFF3E0),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      '${p.emergencia ? "🚨 EMERGENCIA • " : ""}Parada ${p.orden > 0 ? p.orden : 1}${_distanciaKm != null ? " • ${_distanciaKm!.toStringAsFixed(1)} km" : ""}',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                        color: p.emergencia ? Colors.red.shade900 : const Color(0xFFEF6C00),
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  'Bs. ${p.totalPrecio.toStringAsFixed(2)}',
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF00C853)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              c?.nombre ?? 'Cliente',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            Text(
              c?.direccion ?? 'Sin dirección',
              style: const TextStyle(fontSize: 12, color: Colors.black87),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 10),

            // Botones de acción
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () => _mostrarVentanaEntregaCliente(p),
                    icon: const Icon(Icons.check_circle_outline, size: 18, color: Colors.white),
                    label: const Text('LLEGUÉ AL CLIENTE', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 12)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF00C853),
                      padding: const EdgeInsets.symmetric(vertical: 11),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFF00897B),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: const Icon(Icons.phone, color: Colors.white, size: 18),
                  tooltip: 'Llamar al cliente',
                  onPressed: () => _llamarCliente(c?.celular),
                ),
                const SizedBox(width: 4),
                IconButton(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFF0D47A1),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  icon: const Icon(Icons.navigation, color: Colors.white, size: 18),
                  tooltip: 'Abrir en Google Maps externo',
                  onPressed: () => _abrirNavegacionExterna(c),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================================
  // PESTAÑA 3: "ENTREGADOS" (Con desglose QR y Efectivo)
  // =========================================================================

  Widget _buildTabEntregados() {
    if (_entregados.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _cargarPedidos(),
        child: ListView(
          children: const [
            SizedBox(height: 100),
            Icon(Icons.assignment_turned_in_outlined, size: 70, color: Colors.grey),
            SizedBox(height: 12),
            Center(
              child: Text('No hay pedidos entregados hoy todavía.', style: TextStyle(color: Colors.grey, fontSize: 16)),
            ),
          ],
        ),
      );
    }

    // Filtrar por ruta si seleccionó alguna específica
    final listaFiltrada = _entregados.where((p) {
      if (_filtroRutaEntregados == 'TODAS') return true;
      return (p.ruta ?? 'A').toUpperCase().trim() == _filtroRutaEntregados;
    }).toList();

    // Cálculos de totales
    double totalEfectivo = 0.0;
    double totalQR = 0.0;

    for (final p in listaFiltrada) {
      final met = (p.metodoPago ?? '').toLowerCase();
      if (met.contains('qr') || met.contains('transf')) {
        totalQR += p.totalPrecio;
      } else {
        totalEfectivo += p.totalPrecio;
      }
    }
    final totalGeneral = totalEfectivo + totalQR;

    return RefreshIndicator(
      onRefresh: () => _cargarPedidos(),
      child: Column(
        children: [
          // Tarjeta de Resumen con montos en Efectivo y QR
          Container(
            margin: const EdgeInsets.all(12),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF0D47A1), Color(0xFF1976D2)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
              boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, 3))],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Total Entregas Hoy', style: TextStyle(color: Colors.white70, fontSize: 12)),
                        Text(
                          '${listaFiltrada.length} pedidos',
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('Total Cobrado', style: TextStyle(color: Colors.white70, fontSize: 12)),
                        Text(
                          'Bs. ${totalGeneral.toStringAsFixed(2)}',
                          style: const TextStyle(
                            color: Color(0xFF00E676),
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const Divider(color: Colors.white24, height: 20),

                // Desglose: Efectivo y QR
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('💵 Efectivo', style: TextStyle(color: Colors.white70, fontSize: 11)),
                            Text(
                              'Bs. ${totalEfectivo.toStringAsFixed(2)}',
                              style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('📱 QR / Transf.', style: TextStyle(color: Colors.white70, fontSize: 11)),
                            Text(
                              'Bs. ${totalQR.toStringAsFixed(2)}',
                              style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Filtro por Ruta (Todas, A, B, C, D)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              children: [
                const Text('Filtrar:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black54)),
                const SizedBox(width: 8),
                for (final f in ['TODAS', 'A', 'B', 'C', 'D']) ...[
                  GestureDetector(
                    onTap: () => setState(() => _filtroRutaEntregados = f),
                    child: Container(
                      margin: const EdgeInsets.only(right: 6),
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: _filtroRutaEntregados == f ? const Color(0xFF0D47A1) : Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: _filtroRutaEntregados == f ? const Color(0xFF0D47A1) : Colors.grey.shade300,
                        ),
                      ),
                      child: Text(
                        f == 'TODAS' ? 'Todas' : 'Ruta $f',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: _filtroRutaEntregados == f ? Colors.white : Colors.black87,
                        ),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 8),

          // Lista de pedidos entregados
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: listaFiltrada.length,
              itemBuilder: (ctx, i) {
                final p = listaFiltrada[i];
                final c = p.cliente;
                final esQr = (p.metodoPago ?? '').toLowerCase().contains('qr');

                return Card(
                  elevation: 1.5,
                  margin: const EdgeInsets.only(bottom: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: ListTile(
                    onTap: () => _mostrarVentanaEntregaCliente(p),
                    leading: CircleAvatar(
                      backgroundColor: Colors.green.shade50,
                      child: const Icon(Icons.check_circle, color: Color(0xFF00C853)),
                    ),
                    title: Text(c?.nombre ?? 'Cliente', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Parada ${p.orden > 0 ? p.orden : 1} • Ruta ${p.ruta ?? 'A'}', style: TextStyle(fontSize: 11, color: Colors.grey.shade700, fontWeight: FontWeight.w600)),
                        Text(c?.direccion ?? 'S/D', style: const TextStyle(fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                      ],
                    ),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          'Bs. ${p.totalPrecio.toStringAsFixed(2)}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF00C853)),
                        ),
                        const SizedBox(height: 2),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: esQr ? Colors.purple.shade50 : Colors.green.shade50,
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: esQr ? Colors.purple : Colors.green),
                          ),
                          child: Text(
                            esQr ? '📱 QR' : '💵 Efectivo',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: esQr ? Colors.purple : Colors.green,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// Elemento auxiliar para cada fila de producto en el modal de finalizar entrega
class _FilaProductoItem {
  ProductoItem producto;
  double precioUnitario;
  int cantidad;
  final TextEditingController cantidadController;

  _FilaProductoItem({
    required this.producto,
    required this.precioUnitario,
    required this.cantidad,
  }) : cantidadController = TextEditingController(text: cantidad.toString());

  double get subtotal => precioUnitario * cantidad;
}

/// Modal "Finalizar entrega" idéntico al sistema web
class _DialogoFinalizarEntrega extends StatefulWidget {
  final Pedido pedido;
  final ApiService api;
  final List<Pedido>? pedidosEntregados;

  const _DialogoFinalizarEntrega({
    Key? key,
    required this.pedido,
    required this.api,
    this.pedidosEntregados,
  }) : super(key: key);

  @override
  State<_DialogoFinalizarEntrega> createState() => _DialogoFinalizarEntregaState();
}

class _DialogoFinalizarEntregaState extends State<_DialogoFinalizarEntrega> {
  List<ProductoItem> _catalogo = [];
  final List<_FilaProductoItem> _filas = [];
  String _metodoPago = '';
  bool _esUltimaCompra = false;

  @override
  void initState() {
    super.initState();
    if (widget.pedido.estado == 'Entregado') {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.pop(context);
      });
      return;
    }
    _inicializarProductos();
  }

  void _inicializarProductos() {
    final Map<int, ProductoItem> mapaProds = {};

    // 1. Cargar del API si están disponibles
    if (widget.api.productosDisponibles.isNotEmpty) {
      for (final p in widget.api.productosDisponibles) {
        mapaProds[p.id] = p;
      }
    }

    // 2. Si estaba vacío, usar lista de catálogo de respaldo
    if (mapaProds.isEmpty) {
      final fallback = [
        ProductoItem(id: 1, nombre: 'Agua Regular', precio: 16.0),
        ProductoItem(id: 2, nombre: 'Agua Alcalina', precio: 23.0),
        ProductoItem(id: 3, nombre: 'Botellón + Agua Regular', precio: 50.0),
        ProductoItem(id: 4, nombre: 'Botellón + Agua Alcalina', precio: 60.0),
        ProductoItem(id: 5, nombre: 'Dispensador de Mesa', precio: 45.0),
        ProductoItem(id: 6, nombre: 'Bombita manual', precio: 45.0),
      ];
      for (final p in fallback) {
        mapaProds[p.id] = p;
      }
    }

    _catalogo = mapaProds.values.toList();

    // 3. Determinar qué productos cargar por defecto:
    List<DetallePedido> itemsACargar = [];
    if (widget.pedido.detalles.isNotEmpty) {
      // 3.1 Si el pedido actual ya tiene detalles específicos asignados
      itemsACargar = widget.pedido.detalles;
    } else if (widget.pedido.ultimaCompra.isNotEmpty) {
      // 3.2 Repetir la ÚLTIMA COMPRA del cliente (obtenida del servidor)
      itemsACargar = widget.pedido.ultimaCompra;
      _esUltimaCompra = true;
    } else if (widget.pedidosEntregados != null && widget.pedido.cliente != null) {
      // 3.3 Fallback de sesión: buscar si ya se entregó a este cliente en la sesión actual
      for (final pEnt in widget.pedidosEntregados!) {
        if (pEnt.cliente?.id == widget.pedido.cliente!.id && pEnt.detalles.isNotEmpty) {
          itemsACargar = pEnt.detalles;
          _esUltimaCompra = true;
          break;
        }
      }
    }

    // Inicializar filas con los items determinados
    if (itemsACargar.isNotEmpty) {
      for (final det in itemsACargar) {
        ProductoItem? prodMatch;
        for (final p in _catalogo) {
          if (p.nombre.toLowerCase().trim() == det.producto.toLowerCase().trim()) {
            prodMatch = p;
            break;
          }
        }
        prodMatch ??= _catalogo.first;

        _filas.add(_FilaProductoItem(
          producto: prodMatch,
          precioUnitario: det.precioUnitario > 0 ? det.precioUnitario : prodMatch.precio,
          cantidad: det.cantidad > 0 ? det.cantidad : 1,
        ));
      }
    }

    // 4. Si es CLIENTE NUEVO (sin compras anteriores ni detalles), poner por defecto el primer producto con cantidad = 1
    if (_filas.isEmpty && _catalogo.isNotEmpty) {
      final primerProd = _catalogo.first;
      _filas.add(_FilaProductoItem(
        producto: primerProd,
        precioUnitario: primerProd.precio,
        cantidad: 1,
      ));
    }

    // 5. Cargar productos frescos del servidor de fondo
    widget.api.getProductos().then((prods) {
      if (mounted && prods.isNotEmpty) {
        setState(() {
          final Map<int, ProductoItem> m = {};
          for (final p in prods) {
            m[p.id] = p;
          }
          _catalogo = m.values.toList();
        });
      }
    });
  }

  double get _totalGeneral {
    double total = 0.0;
    for (final f in _filas) {
      total += f.subtotal;
    }
    return total;
  }

  @override
  void dispose() {
    for (final f in _filas) {
      f.cantidadController.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const double tableWidth = 430.0;

    return Dialog(
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 24),
      clipBehavior: Clip.antiAlias,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 480),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // =========================
              // ENCABEZADO MODAL
              // =========================
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Finalizar entrega',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF212529),
                      ),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      borderRadius: BorderRadius.circular(4),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.grey.shade400),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: const Icon(Icons.remove, size: 14, color: Color(0xFF6C757D)),
                      ),
                    ),
                  ],
                ),
              ),
              Container(height: 1, color: const Color(0xFFDEE2E6)),

              // =========================
              // CUERPO MODAL
              // =========================
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Aviso informativo: Última compra habitual vs Cliente nuevo
                    if (_esUltimaCompra) ...[
                      Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE8F5E9),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: const Color(0xFF81C784)),
                        ),
                        child: Row(
                          children: const [
                            Icon(Icons.history, size: 16, color: Color(0xFF2E7D32)),
                            SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Repitiendo última compra registrada de este cliente',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF2E7D32),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ] else if (widget.pedido.detalles.isEmpty) ...[
                      Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE3F2FD),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: const Color(0xFF90CAF9)),
                        ),
                        child: Row(
                          children: const [
                            Icon(Icons.person_add_alt_1, size: 16, color: Color(0xFF1565C0)),
                            SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Cliente nuevo o sin compras previas (Cantidad inicial: 1)',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF1565C0),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Contenedor scroll horizontal con ancho fijo de tabla
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: SizedBox(
                        width: tableWidth,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Encabezado de la tabla (thead-light)
                            Container(
                              width: tableWidth,
                              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                              decoration: const BoxDecoration(
                                color: Color(0xFFF1F3F5),
                                border: Border(
                                  bottom: BorderSide(color: Color(0xFFDEE2E6)),
                                ),
                              ),
                              child: Row(
                                children: const [
                                  SizedBox(
                                    width: 140,
                                    child: Text(
                                      'Producto',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF495057)),
                                    ),
                                  ),
                                  SizedBox(
                                    width: 75,
                                    child: Text(
                                      'Precio ref.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF495057)),
                                    ),
                                  ),
                                  SizedBox(
                                    width: 55,
                                    child: Text(
                                      'Cantidad',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF495057)),
                                    ),
                                  ),
                                  SizedBox(
                                    width: 80,
                                    child: Text(
                                      'Total (Bs)',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF495057)),
                                    ),
                                  ),
                                  SizedBox(
                                    width: 45,
                                    child: Text(
                                      'Acción',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF495057)),
                                    ),
                                  ),
                                ],
                              ),
                            ),

                            // Filas de productos
                            ..._filas.asMap().entries.map((entry) {
                              final idx = entry.key;
                              final f = entry.value;
                              final int selectedId = _catalogo.any((p) => p.id == f.producto.id)
                                  ? f.producto.id
                                  : _catalogo.first.id;

                              return Container(
                                width: tableWidth,
                                padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
                                decoration: const BoxDecoration(
                                  border: Border(
                                    bottom: BorderSide(color: Color(0xFFF1F3F5)),
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    // 1. Selector de Producto
                                    SizedBox(
                                      width: 140,
                                      height: 38,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 6),
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          borderRadius: BorderRadius.circular(4),
                                          border: Border.all(color: const Color(0xFFCED4DA)),
                                        ),
                                        child: DropdownButtonHideUnderline(
                                          child: DropdownButton<int>(
                                            isExpanded: true,
                                            value: selectedId,
                                            icon: const Icon(Icons.keyboard_arrow_down, color: Color(0xFF495057), size: 18),
                                            style: const TextStyle(fontSize: 12, color: Color(0xFF212529)),
                                            items: _catalogo.map((p) => DropdownMenuItem<int>(
                                              value: p.id,
                                              child: Text(p.nombre, overflow: TextOverflow.ellipsis, maxLines: 1),
                                            )).toList(),
                                            onChanged: (newId) {
                                              if (newId != null) {
                                                final nuevo = _catalogo.firstWhere((p) => p.id == newId);
                                                setState(() {
                                                  f.producto = nuevo;
                                                  f.precioUnitario = nuevo.precio;
                                                });
                                              }
                                            },
                                          ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 4),

                                    // 2. Precio ref.
                                    SizedBox(
                                      width: 75,
                                      child: Text(
                                        '${f.precioUnitario.toStringAsFixed(2)} Bs',
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF6C757D)),
                                      ),
                                    ),
                                    const SizedBox(width: 4),

                                    // 3. Cantidad editable
                                    SizedBox(
                                      width: 55,
                                      height: 38,
                                      child: Container(
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          borderRadius: BorderRadius.circular(4),
                                          border: Border.all(color: const Color(0xFFCED4DA)),
                                        ),
                                        alignment: Alignment.center,
                                        child: TextField(
                                          controller: f.cantidadController,
                                          keyboardType: TextInputType.number,
                                          textAlign: TextAlign.center,
                                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                                          decoration: const InputDecoration(
                                            isDense: true,
                                            contentPadding: EdgeInsets.symmetric(vertical: 6),
                                            border: InputBorder.none,
                                          ),
                                          onChanged: (val) {
                                            final c = int.tryParse(val);
                                            setState(() {
                                              if (c != null && c >= 1) {
                                                f.cantidad = c;
                                              } else if (val.isEmpty) {
                                                f.cantidad = 0;
                                              }
                                            });
                                          },
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 4),

                                    // 4. Total fila (Readonly gris)
                                    SizedBox(
                                      width: 80,
                                      height: 38,
                                      child: Container(
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFE9ECEF),
                                          borderRadius: BorderRadius.circular(4),
                                          border: Border.all(color: const Color(0xFFCED4DA)),
                                        ),
                                        alignment: Alignment.center,
                                        child: Text(
                                          f.subtotal.toStringAsFixed(2).replaceAll('.', ','),
                                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF495057)),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 4),

                                    // 5. Botón Acción (Papelera roja)
                                    SizedBox(
                                      width: 45,
                                      height: 36,
                                      child: InkWell(
                                        onTap: () {
                                          if (_filas.length > 1) {
                                            setState(() {
                                              _filas.removeAt(idx);
                                            });
                                          } else {
                                            ScaffoldMessenger.of(context).showSnackBar(
                                              const SnackBar(content: Text('Debe haber al menos un producto.')),
                                            );
                                          }
                                        },
                                        borderRadius: BorderRadius.circular(4),
                                        child: Container(
                                          decoration: BoxDecoration(
                                            color: const Color(0xFFDC3545),
                                            borderRadius: BorderRadius.circular(4),
                                          ),
                                          alignment: Alignment.center,
                                          child: const Icon(Icons.delete, color: Colors.white, size: 18),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            }),

                            // Línea divisoria y Fila de Total General
                            Container(
                              width: tableWidth,
                              height: 1,
                              color: const Color(0xFFDEE2E6),
                              margin: const EdgeInsets.symmetric(vertical: 8),
                            ),
                            Container(
                              width: tableWidth,
                              padding: const EdgeInsets.symmetric(vertical: 4),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  const Text(
                                    'Total general (Bs):',
                                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF212529)),
                                  ),
                                  const SizedBox(width: 10),
                                  Container(
                                    width: 80,
                                    height: 38,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFE9ECEF),
                                      borderRadius: BorderRadius.circular(4),
                                      border: Border.all(color: const Color(0xFFCED4DA)),
                                    ),
                                    alignment: Alignment.center,
                                    child: Text(
                                      _totalGeneral.toStringAsFixed(2).replaceAll('.', ','),
                                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF212529)),
                                    ),
                                  ),
                                  const SizedBox(width: 49),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Botón "+ Agregar producto" (Cyan / Teal)
                    ElevatedButton.icon(
                      onPressed: () {
                        final nuevoProd = _catalogo.first;
                        setState(() {
                          _filas.add(_FilaProductoItem(
                            producto: nuevoProd,
                            precioUnitario: nuevoProd.precio,
                            cantidad: 1,
                          ));
                        });
                      },
                      icon: const Icon(Icons.add, size: 16, color: Colors.white),
                      label: const Text(
                        'Agregar producto',
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF17A2B8),
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      ),
                    ),

                    const SizedBox(height: 18),

                    // =========================
                    // MÉTODO DE PAGO
                    // =========================
                    const Text(
                      'Método de pago:',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF212529)),
                    ),
                    const SizedBox(height: 8),

                    Container(
                      height: 42,
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(4),
                        border: Border.all(color: const Color(0xFFCED4DA)),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          isExpanded: true,
                          value: _metodoPago.isEmpty ? null : _metodoPago,
                          hint: const Text('Seleccione...', style: TextStyle(color: Color(0xFF6C757D), fontSize: 14)),
                          icon: const Icon(Icons.keyboard_arrow_down, color: Color(0xFF495057), size: 20),
                          style: const TextStyle(fontSize: 14, color: Color(0xFF212529)),
                          items: const [
                            DropdownMenuItem(value: 'Efectivo', child: Text('Efectivo')),
                            DropdownMenuItem(value: 'QR', child: Text('QR')),
                          ],
                          onChanged: (val) {
                            setState(() {
                              _metodoPago = val ?? '';
                            });
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              Container(height: 1, color: const Color(0xFFDEE2E6)),

              // =========================
              // BOTONES CANCELAR Y FINALIZAR
              // =========================
              Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    ElevatedButton(
                      onPressed: () => Navigator.pop(context),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF6C757D),
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      ),
                      child: const Text('Cancelar', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton(
                      onPressed: () {
                        if (_metodoPago.isEmpty) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Por favor seleccione un método de pago.')),
                          );
                          return;
                        }
                        if (_filas.isEmpty) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Debe incluir al menos un producto.')),
                          );
                          return;
                        }
                        for (final f in _filas) {
                          if (f.cantidad <= 0) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('La cantidad de "${f.producto.nombre}" debe ser al menos 1.')),
                            );
                            return;
                          }
                        }

                        final items = _filas.map((f) => {
                          'producto_id': f.producto.id,
                          'cantidad': f.cantidad,
                          'precio_unitario': f.precioUnitario,
                        }).toList();

                        Navigator.pop(context, {
                          'metodo_pago': _metodoPago,
                          'items': items,
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF28A745),
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                      ),
                      child: const Text('Finalizar', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
