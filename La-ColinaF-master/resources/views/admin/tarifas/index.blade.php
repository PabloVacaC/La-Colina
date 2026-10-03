@extends('adminlte::page')

@section('content_header')
    <h1><b>Listado de Tarifas</b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Tarifas registradas</h3>

                    <div class="card-tools">
                        <a href="{{url('/admin/tarifas/create')}}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear nueva tarifa
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <table id="tabla-tarifas" class="table table-bordered table-hover table-striped table-sm">
                        <thead>
                        <tr>
                            <th style="text-align: center">Nro</th>
                            <th style="text-align: center">Zona Desde</th>
                            <th style="text-align: center">Zona Hasta</th>
                            <th style="text-align: center">Distancia (km)</th>
                            <th style="text-align: center">Precio</th>
                            <th style="text-align: center">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($tarifas as $tarifa)
                            <tr>
                                <td style="text-align: center">{{$loop->iteration}}</td>
                                <td>{{ $tarifa->desde }}</td>
                                <td>{{ $tarifa->hasta }}</td>
                                <td style="text-align: center">{{ number_format($tarifa->distancia, 2) }}</td>
                                <td style="text-align: center">{{ number_format($tarifa->precio, 2) }}</td>
                                <td style="text-align: center">
                                    <div class="btn-group" role="group">
                                        <a href="{{url('/admin/tarifas/'.$tarifa->id)}}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                        <a href="{{url('/admin/tarifas/'.$tarifa->id.'/edit')}}" class="btn btn-success btn-sm">
                                            <i class="fas fa-pencil-alt"></i> Editar
                                        </a>
                                        <form action="{{url('/admin/tarifas',$tarifa->id)}}" method="post"
                                            onclick="preguntar{{$tarifa->id}}(event)" id="miFormulario{{$tarifa->id}}">
                                          @csrf
                                          @method('DELETE')
                                          <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Borrar</button>
                                        </form>
                                        <script>
                                            function preguntar{{$tarifa->id}}(event) {
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
                                                        var form = $('#miFormulario{{$tarifa->id}}');
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
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        #tabla-tarifas_wrapper .dt-buttons {
            background-color: transparent;
            box-shadow: none;
            border: none;
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        #tabla-tarifas_wrapper .btn {
            color: #fff;
            border-radius: 4px;
            padding: 5px 15px;
            font-size: 14px;
        }

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
        $("#tabla-tarifas").DataTable({
            "pageLength": 10,
            "language": {
                "emptyTable": "No hay información",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ Tarifas",
                "infoEmpty": "Mostrando 0 a 0 de 0 Tarifas",
                "infoFiltered": "(Filtrado de _MAX_ total Tarifas)",
                "lengthMenu": "Mostrar _MENU_ Tarifas",
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
        }).buttons().container().appendTo('#tabla-tarifas_wrapper .row:eq(0)');
    });
</script>
@stop