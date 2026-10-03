@extends('adminlte::page')

@section('content_header')
    <h1>Configuraciones del sistema</h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Lleno los datos del formulario</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <form action="{{url('admin/configuraciones/create')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-8">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Nombre</label><b> (*)</b>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-university"></i></span>
                                                </div>
                                                <input type="text" class="form-control" value="{{ old('nombre', $configuracion->nombre ?? '') }}" name="nombre" placeholder="Escriba aquí..." required>
                                            </div>
                                            @error('nombre')
                                            <small style="color: red">{{$message}}</small>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Descripción</label><b> (*)</b>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                                                </div>
                                                <input type="text" class="form-control" value="{{ old('descripcion', $configuracion->descripcion ?? '') }}" name="descripcion" placeholder="Escriba aquí..." required>
                                            </div>
                                            @error('descripcion')
                                            <small style="color: red">{{$message}}</small>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Dirección</label><b> (*)</b>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                </div>
                                                <input class="form-control" value="{{ old('direccion', $configuracion->direccion ?? '') }}" name="direccion" placeholder="Escriba aquí..." required>
                                            </div>
                                            @error('direccion')
                                            <small style="color: red">{{$message}}</small>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="">Teléfono</label><b> (*)</b>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                </div>
                                                <input type="text" class="form-control" value="{{ old('telefono', $configuracion->telefono ?? '') }}" name="telefono" placeholder="Escriba aquí..." required>
                                            </div>
                                            @error('telefono')
                                            <small style="color: red">{{$message}}</small>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Email</label><b> (*)</b>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                </div>
                                                <input type="email" class="form-control" value="{{ old('correo_electronico', $configuracion->correo_electronico ?? '') }}"
                                                       name="correo_electronico" placeholder="Escriba aquí..." required>
                                            </div>
                                            @error('correo_electronico')
                                            <small style="color: red">{{$message}}</small>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Sitio Web</label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                                </div>
                                                <input type="text" class="form-control" value="{{ old('web', $configuracion->web ?? '') }}" name="web" placeholder="Escriba aquí...">
                                            </div>
                                            @error('web')
                                            <small style="color: red">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="">Moneda</label> <b> (*)</b>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                                </div>
                                                <select name="divisa" id="" class="form-control" required>
                                                    <option value="">Seleccione una opción</option>
                                                    @foreach ($divisas as $divisa)
                                                        <option value="{{ $divisa['symbol'] }}"
                                                            {{ old('divisa', $configuracion->divisa ?? '') == $divisa['symbol'] ? 'selected' : '' }}>
                                                            {{ $divisa['name'] . ' (' . $divisa['symbol'] . ')' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @error('divisa')
                                            <small style="color: red">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="logo">Logo</label> <b> (*)</b>
                                    <input type="file" id="file" name="logo" accept=".jpg, .jpeg, .png" class="form-control">
                                    @error('logo')
                                    <small style="color: red;">{{$message}}</small>
                                    @enderror
                                    <br>
                                    <center>
                                        <output id="list">
                                            @if(isset($configuracion->logo))
                                                <img class="thumb thumbnail" src="{{url($configuracion->logo)}}" width="200px" title="Logo">
                                            @endif
                                        </output>
                                    </center>
                                    <script>
                                        function archivo(evt) {
                                            var files = evt.target.files; // FileList object
                                            // Obtenemos la imagen del campo "file".
                                            for (var i = 0, f; f = files[i]; i++) {
                                                //Solo admitimos imágenes.
                                                if (!f.type.match('image.*')) {
                                                    continue;
                                                }
                                                var reader = new FileReader();
                                                reader.onload = (function (theFile) {
                                                    return function (e) {
                                                        // Insertamos la imagen
                                                        document.getElementById("list").innerHTML = ['<img class="thumb thumbnail" src="',e.target.result, '" width="70%" title="', escape(theFile.name), '"/>'].join('');
                                                    };
                                                })(f);
                                                reader.readAsDataURL(f);
                                            }
                                        }
                                        document.getElementById('file').addEventListener('change', archivo, false);
                                    </script>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{ url('/admin') }}" class="btn btn-default"><i
                                            class="fas fa-arrow-left"></i> Cancelar</a>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i>
                                        Guardar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- /.card-body -->
            </div>
        </div>
    </div>
@stop

@section('css')
    {{-- Add here extra stylesheets --}}
    {{-- <link rel="stylesheet" href="/css/admin_custom.css"> --}}
@stop

@section('js')

@stop
