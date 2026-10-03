@extends('adminlte::page')

@section('content_header')
    <h1><b>Motoqueros/Editar motoquero</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">Editar los datos del motoquero</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <form action="{{ url('/admin/motoqueros/'.$motoquero->id) }}" method="post">
                        @csrf
                        @method('PUT')
                       
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="">Nombre del rol</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user-check"></i></span>
                                        </div>
                                        <select name="rol" id="" class="form-control" style="pointer-events: none">
                                            <option value="">Seleccione un rol...</option>
                                            @foreach($roles as $rol)
                                            @if($rol->name == 'MOTOQUERO')
                                                <option value="{{$rol->name}}" {{ $rol->name == 'MOTOQUERO' ? 'selected':'' }}>{{$rol->name}}</option>
                                            @else
                                                <option value="">No existe el rol</option>
                                            @endif
                                        @endforeach
                                        </select>
                                    </div>
                                    @error('rol')
                                    <small style="color: red">{{$message}}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="nombres">Nombres</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" name="nombres" id="nombres" value="{{ old('nombres', $motoquero->nombres) }}" required>
                                    </div>
                                    @error('nombres')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="apellidos">Apellidos</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" name="apellidos" id="apellidos" value="{{ old('apellidos', $motoquero->apellidos) }}" required>
                                    </div>
                                    @error('apellidos')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="ci">Cédula de Identidad</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-id-card"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" name="ci" id="ci" value="{{ old('ci', $motoquero->ci) }}" required>
                                    </div>
                                    @error('ci')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de Nacimiento</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-calendar-alt"></i>
                                            </span>
                                        </div>
                                        <input type="date" class="form-control" name="fecha_nacimiento" id="fecha_nacimiento" 
                                        value="{{ old('fecha_nacimiento', $motoquero->fecha_nacimiento) }}" required>
                                    </div>
                                    @error('fecha_nacimiento')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                    
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="genero">Género</label><b> (*)</b>
                                    <select class="form-control" name="genero" id="genero" required>
                                        <option value="">Seleccione...</option>
                                        <option value="M" {{ old('genero', $motoquero->genero) == 'M' ? 'selected' : '' }}>Masculino</option>
                                        <option value="F" {{ old('genero', $motoquero->genero) == 'F' ? 'selected' : '' }}>Femenino</option>
                                        <option value="Otro" {{ old('genero', $motoquero->genero) == 'Otro' ? 'selected' : '' }}>Otro</option>
                                    </select>
                                    @error('genero')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                    
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="celular">Celular</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-phone-alt"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" name="celular" id="celular" value="{{ old('celular', $motoquero->celular) }}" required>
                                    </div>
                                    @error('celular')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="email">Email</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                        </div>
                                        <input type="email" class="form-control" name="email" id="email" value="{{ old('email', $motoquero->usuario->email) }}" required>
                                    </div>
                                    @error('email')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="placa">Placa de Moto</label>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-motorcycle"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" name="placa" id="placa" value="{{ old('placa', $motoquero->placa) }}">
                                    </div>
                                    @error('placa')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-9">
                                <div class="form-group">
                                    <label for="direccion">Dirección de domicilio</label><b> (*)</b>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </span>
                                        </div>
                                        <input type="text" class="form-control" name="direccion" id="direccion" value="{{ old('direccion', $motoquero->direccion) }}" required>
                                    </div>
                                    @error('direccion')
                                        <small style="color: red">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>


                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Nueva contraseña</label>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                        </div>
                                        <input type="password"
                                            name="password"
                                            class="form-control"
                                            placeholder="Dejar vacío para no cambiar">
                                    </div>
                                    @error('password')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Confirmar nueva contraseña</label>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                        </div>
                                        <input type="password"
                                            name="password_confirmation"
                                            class="form-control"
                                            placeholder="Repita la contraseña">
                                    </div>
                                </div>
                            </div>


                        </div>




                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{url('/admin/motoqueros')}}" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-success">Actualizar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
@stop

@section('css')
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Puedes agregar scripts específicos para la edición aquí
        });
    </script>
@stop