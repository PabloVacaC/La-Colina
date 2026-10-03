import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';
import 'api_service.dart';

class LocationTrackingState {
  final bool isTracking;
  final Position? lastPosition;
  final DateTime? lastSentTime;
  final String statusMessage;
  final bool hasError;

  LocationTrackingState({
    required this.isTracking,
    this.lastPosition,
    this.lastSentTime,
    required this.statusMessage,
    this.hasError = false,
  });
}

class LocationService {
  static final LocationService _instance = LocationService._internal();
  factory LocationService() => _instance;
  LocationService._internal();

  Timer? _timer;
  int? _activeMotoqueroId;

  final ValueNotifier<LocationTrackingState> stateNotifier = ValueNotifier(
    LocationTrackingState(
      isTracking: false,
      statusMessage: 'GPS inactivo',
    ),
  );

  /// Solicitar permisos de GPS
  Future<bool> checkAndRequestPermissions() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      stateNotifier.value = LocationTrackingState(
        isTracking: false,
        statusMessage: 'El servicio de ubicación (GPS) está apagado.',
        hasError: true,
      );
      return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        stateNotifier.value = LocationTrackingState(
          isTracking: false,
          statusMessage: 'Permiso de ubicación denegado.',
          hasError: true,
        );
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      stateNotifier.value = LocationTrackingState(
        isTracking: false,
        statusMessage: 'Permiso de ubicación denegado permanentemente en ajustes.',
        hasError: true,
      );
      return false;
    }

    return true;
  }

  /// Iniciar transmisión periódica de ubicación
  Future<void> startTracking(int motoqueroId, {int intervalSeconds = 8}) async {
    _activeMotoqueroId = motoqueroId;

    final hasPermission = await checkAndRequestPermissions();
    if (!hasPermission) return;

    _timer?.cancel();

    stateNotifier.value = LocationTrackingState(
      isTracking: true,
      statusMessage: 'Conectando con satélites GPS...',
    );

    // Enviar primera posición inmediatamente
    _enviarPosicionActual();

    // Iniciar temporizador
    _timer = Timer.periodic(Duration(seconds: intervalSeconds), (_) {
      _enviarPosicionActual();
    });
  }

  Future<void> _enviarPosicionActual() async {
    if (_activeMotoqueroId == null) return;

    try {
      Position position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 8),
        ),
      );

      final success = await ApiService().enviarUbicacion(
        _activeMotoqueroId!,
        position.latitude,
        position.longitude,
      );

      final now = DateTime.now();
      final horaStr = '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';

      if (success) {
        stateNotifier.value = LocationTrackingState(
          isTracking: true,
          lastPosition: position,
          lastSentTime: now,
          statusMessage: 'Transmitiendo GPS en vivo ($horaStr)',
          hasError: false,
        );
      } else {
        stateNotifier.value = LocationTrackingState(
          isTracking: true,
          lastPosition: position,
          lastSentTime: now,
          statusMessage: 'Error de conexión con el servidor',
          hasError: true,
        );
      }
    } catch (e) {
      stateNotifier.value = LocationTrackingState(
        isTracking: true,
        statusMessage: 'Buscando señal GPS...',
        hasError: false,
      );
    }
  }

  /// Detener transmisión
  void stopTracking() {
    _timer?.cancel();
    _timer = null;
    _activeMotoqueroId = null;
    stateNotifier.value = LocationTrackingState(
      isTracking: false,
      statusMessage: 'GPS en pausa',
    );
  }
}
