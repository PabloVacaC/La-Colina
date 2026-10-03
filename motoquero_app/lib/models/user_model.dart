class Motoquero {
  final int id;
  final String nombres;
  final String apellidos;
  final String ci;
  final String celular;
  final String direccion;
  final String placa;
  final String? fechaNacimiento;

  Motoquero({
    required this.id,
    required this.nombres,
    required this.apellidos,
    required this.ci,
    required this.celular,
    required this.direccion,
    required this.placa,
    this.fechaNacimiento,
  });

  String get nombreCompleto => '$nombres $apellidos';

  factory Motoquero.fromJson(Map<String, dynamic> json) {
    return Motoquero(
      id: json['id'] ?? 0,
      nombres: json['nombres'] ?? '',
      apellidos: json['apellidos'] ?? '',
      ci: json['ci'] ?? '',
      celular: json['celular'] ?? '',
      direccion: json['direccion'] ?? '',
      placa: json['placa'] ?? 'S/P',
      fechaNacimiento: json['fecha_nacimiento'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nombres': nombres,
      'apellidos': apellidos,
      'ci': ci,
      'celular': celular,
      'direccion': direccion,
      'placa': placa,
      'fecha_nacimiento': fechaNacimiento,
    };
  }
}

class UserSession {
  final int userId;
  final String userName;
  final String email;
  final String token;
  final Motoquero motoquero;

  UserSession({
    required this.userId,
    required this.userName,
    required this.email,
    required this.token,
    required this.motoquero,
  });

  factory UserSession.fromJson(Map<String, dynamic> json) {
    return UserSession(
      userId: json['user']?['id'] ?? 0,
      userName: json['user']?['name'] ?? '',
      email: json['user']?['email'] ?? '',
      token: json['token'] ?? '',
      motoquero: Motoquero.fromJson(json['motoquero'] ?? {}),
    );
  }
}
