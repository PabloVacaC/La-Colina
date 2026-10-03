@extends('adminlte::page')

@section('content_header')
    <h1><b>Detalles de la Tarifa</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Información de la Tarifa</h3>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Zona de Origen:</label>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" value="{{ $tarifa->desde }}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Zona de Destino:</label>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-map-marker"></i></span>
                                    </div>
                                    <input type="text" class="form-control" value="{{ $tarifa->hasta }}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Distancia (km):</label>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-route"></i></span>
                                    </div>
                                    <input type="text" class="form-control" value="{{ number_format($tarifa->distancia, 2) }}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Precio:</label>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                    </div>
                                    <input type="text" class="form-control" value="{{ number_format($tarifa->precio, 2) }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <a href="{{ route('admin.tarifas.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </a>
                                @can('edit-tarifas')
                                    <a href="{{ route('admin.tarifas.edit', $tarifa->id) }}" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .input-group-text {
            background-color: #f8f9fa;
        }
        .form-control[readonly] {
            background-color: #f8f9fa;
            opacity: 1;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Puedes agregar funcionalidad JS si es necesario
        });
    </script>
@stop