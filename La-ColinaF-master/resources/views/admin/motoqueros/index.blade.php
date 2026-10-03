@extends('adminlte::page')

@section('content_header')
    <h1><b>Listado de motoqueros</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Motoqueros registrados</h3>

                    <div class="card-tools">
                        <a href="{{url('/admin/motoqueros/create')}}" class="btn btn-primary"> Crear nuevo</a>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="example1" class="table table-bordered table-hover table-striped table-sm">
                        <thead>
                        <tr>
                            <th style="text-align: center">Nro</th>
                            <th style="text-align: center">Rol</th>
                            <th style="text-align: center">Nombres</th>
                            <th style="text-align: center">Apellidos</th>
                            <th style="text-align: center">Cédula</th>
                            <th style="text-align: center">Fecha de nacimiento</th>
                            <th style="text-align: center">Género</th>
                            <th style="text-align: center">Celular</th>
                            <th style="text-align: center">Email</th>
                            <th style="text-align: center">Dirección</th>
                            <th style="text-align: center">Placa de la moto</th>
                            <th style="text-align: center">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $contador = 1;
                        @endphp
                        @foreach($motoqueros as $motoquero)
                            <tr>
                                <td style="text-align: center">{{$contador++}}</td>
                                <td>{{ $motoquero->usuario->roles->pluck('name')->implode(', ') }}</td>
                                <td>{{ $motoquero->nombres }}</td>
                                <td>{{ $motoquero->apellidos }}</td>
                                <td>{{ $motoquero->ci }}</td>
                                <td>{{ $motoquero->fecha_nacimiento }}</td>
                                <td>{{ $motoquero->genero }}</td>
                                <td>{{ $motoquero->celular }}</td>
                                <td>{{ $motoquero->usuario->email }}</td>
                                <td>{{ $motoquero->direccion }}</td>
                                <td>{{ $motoquero->placa }}</td>
                                <td style="text-align: center">
                                    <div class="btn-group" role="group" aria-label="Basic example">
                                        <a href="{{url('/admin/motoqueros/'.$motoquero->id)}}" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> Ver</a>
                                        <a href="{{url('/admin/motoqueros/'.$motoquero->id.'/edit')}}" class="btn btn-success btn-sm"><i class="fas fa-pencil-alt"></i> Editar</a>
                                        <form action="{{url('/admin/motoqueros',$motoquero->id)}}" method="post"
                                              onclick="preguntar{{$motoquero->id}}(event)" id="miFormulario{{$motoquero->id}}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Borrar</button>
                                        </form>
                                        <script>
                                            function preguntar{{$motoquero->id}}(event) {
                                                event.preventDefault();
                                                Swal.fire({
                                                    title: '¿Desea eliminar esta registro?',
                                                    text: '',
                                                    icon: 'question',
                                                    showDenyButton: true,
                                                    confirmButtonText: 'Eliminar',
                                                    confirmButtonColor: '#a5161d',
                                                    denyButtonColor: '#270a0a',
                                                    denyButtonText: 'Cancelar',
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        var form = $('#miFormulario{{$motoquero->id}}');
                                                        form.submit();
                                                    }
                                                });
                                            }
                                        </script>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
@stop

@section('css')
    <style>
        /* Fondo transparente y sin borde en el contenedor */
        #example1_wrapper .dt-buttons {
            background-color: transparent;
            box-shadow: none;
            border: none;
            display: flex;
            justify-content: center; /* Centrar los botones */
            gap: 10px; /* Espaciado entre botones */
            margin-bottom: 15px; /* Separar botones de la tabla */
        }

        /* Estilo personalizado para los botones */
        #example1_wrapper .btn {
            color: #fff; /* Color del texto en blanco */
            border-radius: 4px; /* Bordes redondeados */
            padding: 5px 15px; /* Espaciado interno */
            font-size: 14px; /* Tamaño de fuente */
        }

        /* Colores por tipo de botón */
        .btn-danger { background-color: #dc3545; border: none; }
        .btn-success { background-color: #28a745; border: none; }
        .btn-info { background-color: #17a2b8; border: none; }
        .btn-warning { background-color: #ffc107; color: #212529; border: none; }
        .btn-default { background-color: #6e7176; color: #212529; border: none; }
    </style>
@stop

@section('js')
    <script>
        $(function () {
            $("#example1").DataTable({
                "pageLength": 10,
                "language": {
                    "emptyTable": "No hay información",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ Motoqueros",
                    "infoEmpty": "Mostrando 0 a 0 de 0 Motoqueros",
                    "infoFiltered": "(Filtrado de _MAX_ total Motoqueros)",
                    "lengthMenu": "Mostrar _MENU_ Motoqueros",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "search": "Buscador:",
                    "zeroRecords": "Sin resultados encontrados",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    }
                },
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                buttons: [
                    { text: '<i class="fas fa-copy"></i> COPIAR', extend: 'copy', className: 'btn btn-default' },
                    { text: '<i class="fas fa-file-pdf"></i> PDF', extend: 'pdf', className: 'btn btn-danger' },
                    { text: '<i class="fas fa-file-csv"></i> CSV', extend: 'csv', className: 'btn btn-info' },
                    { text: '<i class="fas fa-file-excel"></i> EXCEL', extend: 'excel', className: 'btn btn-success' },
                    { text: '<i class="fas fa-print"></i> IMPRIMIR', extend: 'print', className: 'btn btn-warning' }
                ]
            }).buttons().container().appendTo('#example1_wrapper .row:eq(0)');
        });
    </script>
@stop
