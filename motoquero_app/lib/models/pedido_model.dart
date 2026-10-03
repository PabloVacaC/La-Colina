class Cliente {
  final int id;
  final String nombre;
  final String celular;
  final String? referenciaCelular;
  final String direccion;
  final double? latitud;
  final double? longitud;
  final String? ubicacionGps;
  final String? imagenCasa;
  String? imagenCasaUrl;

  Cliente({
    required this.id,
    required this.nombre,
    required this.celular,
    this.referenciaCelular,
    required this.direccion,
    this.latitud,
    this.longitud,
    this.ubicacionGps,
    this.imagenCasa,
    this.imagenCasaUrl,
  });

  factory Cliente.fromJson(Map<String, dynamic> json) {
    return Cliente(
      id: json['id'] ?? 0,
      nombre: json['nombre'] ?? 'Sin nombre',
      celular: json['celular'] ?? '',
      referenciaCelular: json['referencia_celular'],
      direccion: json['direccion'] ?? 'Sin dirección',
      latitud: json['latitud'] != null ? double.tryParse(json['latitud'].toString()) : null,
      longitud: json['longitud'] != null ? double.tryParse(json['longitud'].toString()) : null,
      ubicacionGps: json['ubicacion_gps'],
      imagenCasa: json['imagen_casa'],
      imagenCasaUrl: json['imagen_casa_url'],
    );
  }
}

class DetallePedido {
  final int id;
  final String producto;
  final String? detalle;
  final int cantidad;
  final double precioUnitario;
  final double precioTotal;

  DetallePedido({
    required this.id,
    required this.producto,
    this.detalle,
    required this.cantidad,
    required this.precioUnitario,
    required this.precioTotal,
  });

  factory DetallePedido.fromJson(Map<String, dynamic> json) {
    return DetallePedido(
      id: json['id'] ?? 0,
      producto: json['producto'] ?? '',
      detalle: json['detalle'],
      cantidad: json['cantidad'] ?? 1,
      precioUnitario: (json['precio_unitario'] != null) ? double.parse(json['precio_unitario'].toString()) : 0.0,
      precioTotal: (json['precio_total'] != null) ? double.parse(json['precio_total'].toString()) : 0.0,
    );
  }
}

class Pedido {
  final int id;
  String estado;
  final double totalPrecio;
  String? metodoPago;
  String? qrPagoEstado;
  final int orden;
  final String? ruta;
  final bool emergencia;
  final String? createdAt;
  final Cliente? cliente;
  final List<DetallePedido> detalles;

  Pedido({
    required this.id,
    required this.estado,
    required this.totalPrecio,
    this.metodoPago,
    this.qrPagoEstado,
    required this.orden,
    this.ruta,
    required this.emergencia,
    this.createdAt,
    this.cliente,
    required this.detalles,
  });

  factory Pedido.fromJson(Map<String, dynamic> json) {
    var rawDetalles = json['detalles'] as List? ?? [];
    List<DetallePedido> detallesList = rawDetalles.map((d) => DetallePedido.fromJson(d)).toList();

    return Pedido(
      id: json['id'] ?? 0,
      estado: json['estado'] ?? 'Asignado',
      totalPrecio: json['total_precio'] != null ? double.parse(json['total_precio'].toString()) : 0.0,
      metodoPago: json['metodo_pago'],
      qrPagoEstado: json['qr_pago_estado'],
      orden: json['orden'] ?? 0,
      ruta: json['ruta'],
      emergencia: json['emergencia'] == true,
      createdAt: json['created_at'],
      cliente: json['cliente'] != null ? Cliente.fromJson(json['cliente']) : null,
      detalles: detallesList,
    );
  }
}
