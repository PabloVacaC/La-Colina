@extends('adminlte::page')

@section('content_header')
    <h1><b>Detalles del Motoquero</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Información del Motoquero</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Rol:</label>
                                <p class="form-control-static">{{ $motoquero?->usuario?->roles?->pluck('name')?->implode(', ') ?? 'Sin rol' }}</p> 
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Nombres:</label>
                                <p class="form-control-static">{{ $motoquero?->nombres }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Apellidos:</label>
                                <p class="form-control-static">{{ $motoquero?->apellidos }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Cédula de Identidad:</label>
                                <p class="form-control-static">{{ $motoquero?->ci }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha de Nacimiento:</label>
                                <p class="form-control-static">{{ $motoquero?->fecha_nacimiento ? \Carbon\Carbon::parse($motoquero->fecha_nacimiento)->format('d/m/Y') : '' }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Género:</label>
                                <p class="form-control-static">
                                    @switch($motoquero?->genero)
                                        @case('M')
                                            Masculino
                                            @break
                                        @case('F')
                                            Femenino
                                            @break
                                        @default
                                            Otro
                                    @endswitch
                                </p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Celular:</label>
                                <p class="form-control-static">{{ $motoquero?->celular }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Email:</label>
                                <p class="form-control-static">{{ $motoquero?->usuario?->email ?? 'Sin email' }}</p>
                            </div>
                        </div>
                        
                    </div>

                   

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dirección:</label>
                                <p class="form-control-static">{{ $motoquero->direccion }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Placa de Moto:</label>
                                <p class="form-control-static">{{ $motoquero->placa ?? 'No registrada' }}</p>
                            </div>
                        </div>
                    </div>

                  
                </div>
                <!-- /.card-body -->
                <div class="card-footer">
                   
                    <a href="{{ url('/admin/motoqueros') }}" class="btn btn-secondary float-left">
                        <i class="fas fa-arrow-left"></i> Volver al listado
                    </a>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
@stop

@section('css')
    <style>
        .form-control-static {
            padding: 0.375rem 0.75rem;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            min-height: calc(1.5em + 0.75rem + 2px);
            display: block;
            width: 100%;
        }
        .card-header {
            background-color: #007bff;
            color: white;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
@stop