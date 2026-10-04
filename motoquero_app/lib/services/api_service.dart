import 'dart:convert';
import 'package:http/http.dart' as http;

import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import '../models/pedido_model.dart';

class ApiService {
  static const String defaultBaseUrl = 'http://13.217.89.84:8000/api';
  static const String keyBaseUrl = 'custom_base_url';

  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  String _baseUrl = defaultBaseUrl;
  String? _token;

  String get baseUrl => _baseUrl;
  String? get token => _token;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(keyBaseUrl);
    // Si tenía una IP local de prueba (192.168 o localhost), actualizar a la IP de producción AWS
    if (saved != null && !saved.contains('192.168.') && !saved.contains('localhost')) {
      _baseUrl = saved;
    } else {
      _baseUrl = defaultBaseUrl;
      await prefs.setString(keyBaseUrl, _baseUrl);
    }
    _token = prefs.getString('auth_token');
  }

  Future<void> setBaseUrl(String newUrl) async {
    _baseUrl = newUrl.endsWith('/') ? newUrl.substring(0, newUrl.length - 1) : newUrl;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(keyBaseUrl, _baseUrl);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  /// Login de Motoquero
  Future<UserSession> login(String email, String password) async {
    final url = Uri.parse('$_baseUrl/login');
    final response = await http
        .post(
          url,
          headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
          body: jsonEncode({'email': email.trim(), 'password': password}),
        )
        .timeout(const Duration(seconds: 12));

    final data = jsonDecode(response.body);

    if (response.statusCode == 200 && data['success'] == true) {
      final session = UserSession.fromJson(data);
      _token = session.token;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', session.token);
      await prefs.setString('user_session', jsonEncode(data));
      return session;
    } else {
      throw Exception(data['message'] ?? 'Error de autenticación');
    }
  }

  /// Cargar sesión guardada
  Future<UserSession?> getSavedSession() async {
    final prefs = await SharedPreferences.getInstance();
    final jsonStr = prefs.getString('user_session');
    if (jsonStr != null) {
      try {
        final data = jsonDecode(jsonStr);
        final session = UserSession.fromJson(data);
        _token = session.token;
        return session;
      } catch (_) {
        return null;
      }
    }
    return null;
  }

  /// Cerrar Sesión
  Future<void> logout() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('user_session');
  }

  List<ProductoItem> _productosDisponibles = [];
  List<ProductoItem> get productosDisponibles => _productosDisponibles;

  /// Obtener pedidos del motoquero clasificados
  Future<Map<String, List<Pedido>>> getPedidos(int motoqueroId) async {
    final url = Uri.parse('$_baseUrl/motoquero/$motoqueroId/pedidos');
    final response = await http.get(url, headers: _headers).timeout(const Duration(seconds: 12));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      final asignadosRaw = (data['asignados'] as List? ?? []);
      final enCaminoRaw = (data['en_camino'] as List? ?? []);
      final entregadosRaw = (data['entregados'] as List? ?? []);

      if (data['productos'] != null && data['productos'] is List) {
        _productosDisponibles = (data['productos'] as List)
            .map((item) => ProductoItem.fromJson(item))
            .toList();
      }

      return {
        'asignados': asignadosRaw.map((p) => Pedido.fromJson(p)).toList(),
        'en_camino': enCaminoRaw.map((p) => Pedido.fromJson(p)).toList(),
        'entregados': entregadosRaw.map((p) => Pedido.fromJson(p)).toList(),
      };
    } else {
      throw Exception('Error al obtener pedidos (${response.statusCode})');
    }
  }

  /// Obtener catálogo de productos
  Future<List<ProductoItem>> getProductos() async {
    try {
      final url = Uri.parse('$_baseUrl/productos');
      final response = await http.get(url, headers: _headers).timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['productos'] != null && data['productos'] is List) {
          _productosDisponibles = (data['productos'] as List)
              .map((item) => ProductoItem.fromJson(item))
              .toList();
        }
      }
    } catch (_) {}
    return _productosDisponibles;
  }

  /// Aceptar / Tomar pedido
  Future<bool> tomarPedido(int pedidoId) async {
    final url = Uri.parse('$_baseUrl/pedidos/$pedidoId/tomar');
    final response = await http.post(url, headers: _headers).timeout(const Duration(seconds: 10));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['success'] == true;
    }
    return false;
  }

  /// Rechazar pedido
  Future<bool> rechazarPedido(int pedidoId) async {
    final url = Uri.parse('$_baseUrl/pedidos/$pedidoId/rechazar');
    final response = await http.post(url, headers: _headers).timeout(const Duration(seconds: 10));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['success'] == true;
    }
    return false;
  }

  /// Cancelar entrega de un pedido (por ejemplo, el cliente no sale, no atiende o no se encuentra)
  Future<bool> cancelarPedido(int pedidoId, {String? motivo}) async {
    try {
      final url = Uri.parse('$_baseUrl/pedidos/$pedidoId/cancelar');
      final response = await http
          .post(
            url,
            headers: _headers,
            body: jsonEncode({if (motivo != null) 'motivo': motivo}),
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['success'] == true;
      }
    } catch (_) {}

    // Fallback de compatibilidad si el backend aún no ha desplegado la ruta /cancelar
    return rechazarPedido(pedidoId);
  }

  /// Tomar todos los pedidos de una ruta
  Future<bool> tomarRuta(int motoqueroId, String ruta) async {
    try {
      final url = Uri.parse('$_baseUrl/motoquero/$motoqueroId/tomar-ruta');
      final response = await http
          .post(
            url,
            headers: _headers,
            body: jsonEncode({'ruta': ruta}),
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['success'] == true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Finalizar / Entregar pedido con desglose de productos y método de pago
  Future<bool> finalizarPedido(
    int pedidoId,
    String metodoPago, {
    String? qrPagoEstado,
    List<Map<String, dynamic>>? items,
  }) async {
    final url = Uri.parse('$_baseUrl/pedidos/$pedidoId/finalizar');
    final response = await http
        .post(
          url,
          headers: _headers,
          body: jsonEncode({
            'metodo_pago': metodoPago,
            if (qrPagoEstado != null) 'qr_pago_estado': qrPagoEstado,
            if (items != null) 'items': items,
          }),
        )
        .timeout(const Duration(seconds: 12));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['success'] == true;
    }
    return false;
  }

  /// Subir foto de casa o comprobante para el cliente
  Future<String?> subirFotoCasa(int clienteId, XFile imageFile) async {
    final url = Uri.parse('$_baseUrl/clientes/$clienteId/imagen');
    final request = http.MultipartRequest('POST', url);

    request.headers.addAll({
      'Accept': 'application/json',
      if (_token != null) 'Authorization': 'Bearer $_token',
    });

    request.files.add(
      await http.MultipartFile.fromPath('imagen_casa', imageFile.path),
    );

    final streamedResponse = await request.send().timeout(const Duration(seconds: 30));
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['imagen_url'];
    } else {
      throw Exception('Error al subir imagen: ${response.body}');
    }
  }

  /// Transmitir ubicación GPS al backend
  Future<bool> enviarUbicacion(int motoqueroId, double lat, double lng) async {
    try {
      final url = Uri.parse('$_baseUrl/motoquero/ubicacion');
      final response = await http
          .post(
            url,
            headers: _headers,
            body: jsonEncode({
              'motoquero_id': motoqueroId,
              'latitud': lat,
              'longitud': lng,
            }),
          )
          .timeout(const Duration(seconds: 8));

      return response.statusCode == 200;
    } catch (_) {
      return false;
    }
  }
}

class ProductoItem {
  final int id;
  final String nombre;
  final double precio;

  ProductoItem({required this.id, required this.nombre, required this.precio});

  factory ProductoItem.fromJson(Map<String, dynamic> json) {
    return ProductoItem(
      id: json['id'] ?? 0,
      nombre: json['nombre'] ?? '',
      precio: (json['precio'] != null) ? double.parse(json['precio'].toString()) : 0.0,
    );
  }
}

