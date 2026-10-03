@extends('adminlte::page')

@section('content_header')
    <h1><b>Tarifas / Editar tarifa</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">Editar datos de la tarifa</h3>
                </div>
                
                <div class="card-body">
                    <form action="{{ route('admin.tarifas.update', $tarifa->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="desde">Zona de Origen</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="desde" id="desde" 
                                               value="{{ old('desde', $tarifa->desde) }}" placeholder="Ej: Zona Central" required>
                                    </div>
                                    @error('desde')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hasta">Zona de Destino</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-map-marker"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="hasta" id="hasta" 
                                               value="{{ old('hasta', $tarifa->hasta) }}" placeholder="Ej: Zona Sur" required>
                                    </div>
                                    @error('hasta')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="distancia">Distancia (km)</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-route"></i></span>
                                        </div>
                                        <input type="number" class="form-control" name="distancia" id="distancia" 
                                               value="{{ old('distancia', $tarifa->distancia) }}" step="0.01" min="0.1" 
                                               placeholder="Ej: 5.25" required>
                                    </div>
                                    @error('distancia')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="precio">Precio</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" class="form-control" name="precio" id="precio" 
                                               value="{{ old('precio', $tarifa->precio) }}" step="0.01" min="1" 
                                               placeholder="Ej: 15.50" required>
                                    </div>
                                    @error('precio')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{ route('admin.tarifas.show', $tarifa->id) }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Actualizar Tarifa
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
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
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Validación adicional en cliente
            $('#distancia, #precio').on('input', function() {
                let value = parseFloat($(this).val());
                if (value < 0) {
                    $(this).val(Math.abs(value));
                }
            });
        });
    </script>
@stop