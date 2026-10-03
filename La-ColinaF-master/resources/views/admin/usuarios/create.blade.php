@extends('adminlte::page')

@section('content_header')
    <h1><b>Usuarios / Registrar nuevo usuario</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Complete los datos del formulario</h3>
                </div>
                
                <div class="card-body">
                    <form action="{{ route('admin.usuarios.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">Rol</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                        </div>
                                        <select class="form-control" name="role" id="role" required>
                                            <option value="">Seleccione un rol</option>
                                            @foreach($roles as $role)
                                                @if($role->name != "MOTOQUERO")
                                                    <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                                        {{ $role->name }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('role')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Usuario</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="name" id="name" 
                                               value="{{ old('name') }}" placeholder="Ej: juan carlos" required>
                                    </div>
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                                                        
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="email">Correo Electrónico</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" name="email" id="email" 
                                               value="{{ old('email') }}" placeholder="Ej: usuario@dominio.com" required>
                                    </div>
                                    @error('email')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                           
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="password">Contraseña</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        </div>
                                        <input type="password" class="form-control" name="password" id="password" 
                                               placeholder="Mínimo 8 caracteres" required>
                                    </div>
                                    @error('password')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="password_confirmation">Confirmar Contraseña</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        </div>
                                        <input type="password" class="form-control" name="password_confirmation" 
                                               id="password_confirmation" placeholder="Repita la contraseña" required>
                                    </div>
                                </div>
                            </div>
                            
                           
                        </div>
                        
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Registrar Usuario
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
            $('#phone').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9]/g, ''));
            });
        });
    </script>
@stop