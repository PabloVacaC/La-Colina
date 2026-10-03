@extends('adminlte::page')

@section('content_header')
<h1><b>Bienvenido, {{ Auth::user()->roles->pluck('name')->implode(', ') }}:</b> {{ Auth::user()->name }} </h1>
<hr>
@stop

@section('content')
    <div class="row">

        @if (Auth::user()->roles->pluck('name')->implode(', ') == 'MOTOQUERO')
        <div class="card col-md-6 col-sm-6 col-12">
            <div class="card-header">
                Datos del usuario
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped table-hover table-sm">
                    <tr>
                        <td style="background-color: #d4f4fa;width:200px"><b>Nombres:</b> </td>
                        <td>{{ Auth::user()->motoquero->nombres }}</td>
                    </tr>
                    <tr>
                        <td style="background-color: #d4f4fa"><b>Apellidos:</b> </td>
                        <td>{{ Auth::user()->motoquero->apellidos }}</td>
                    </tr>
                    <tr>
                        <td style="background-color: #d4f4fa"><b>Carnet de identidad:</b> </td>
                        <td>{{ Auth::user()->motoquero->ci }}</td>
                    </tr>
                    <tr>
                        <td style="background-color: #d4f4fa"><b>Fecha de nacimiento:</b> </td>
                        <td>{{ Auth::user()->motoquero->fecha_nacimiento }}</td>
                    </tr>
                    <tr>
                        <td style="background-color: #d4f4fa"><b>Teléfono:</b> </td>
                        <td>{{ Auth::user()->motoquero->celular }}</td>
                    </tr>
                    <tr>
                        <td style="background-color: #d4f4fa"><b>Genero:</b> </td>
                        <td>
                            @if(Auth::user()->motoquero->genero == 'M')
                                Masculino
                            @else
                                Femenino
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #d4f4fa"><b>Dirección:</b> </td>
                        <td>{{ Auth::user()->motoquero->direccion }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/pedidos/motoquero/'.Auth::user()->motoquero->id)}}" class="info-box-icon bg-info">
                    <img src="{{url('/img/pedido-en-linea.gif')}}" alt="">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Pedidos asignados</span>
                    <span class="info-box-number">{{$total_pedidos_asignados}} pedidos nuevos 
                        <img src="{{url('/img/notificacion.gif')}}" width="30px" alt="">
                    </span>
                </div>
            </div>
        </div>
        @endif


        @can('admin.roles.index')
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/roles')}}" class="info-box-icon bg-info">
                    <img src="{{url('/img/roles.gif')}}" alt="">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Roles registrados</span>
                    <span class="info-box-number">{{$total_roles}} roles</span>
                </div>
            </div>
        </div>
        @endcan

        @can('admin.motoqueros.index')
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/motoqueros')}}" class="info-box-icon bg-info">
                    <img src="{{url('/img/moto-electrica.gif')}}" alt="">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Distribuidores registrados</span>
                    <span class="info-box-number">{{$total_motoqueros}} motoqueros</span>
                </div>
            </div>
        </div>
        @endcan

        @can('admin.clientes.index')
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/clientes')}}" class="info-box-icon bg-info">
                    <img src="{{url('/img/cliente.gif')}}" alt="">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Clientes registrados</span>
                    <span class="info-box-number">{{$total_clientes}} clientes</span>
                </div>
            </div>
        </div>
        @endcan

        @can('admin.pedidos.index')
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/pedidos')}}" class="info-box-icon bg-info">
                    <img src="{{url('/img/pedido-en-linea.gif')}}" alt="">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Pedidos registrados</span>
                    <span class="info-box-number">{{$total_pedidos}} pedidos</span>
                </div>
            </div>
        </div>
        @endcan

        @can('admin.usuarios.index')
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/usuarios')}}" class="info-box-icon bg-info">
                    <img src="{{url('/img/usuario.gif')}}" alt="">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Usuarios registrados</span>
                    <span class="info-box-number">{{$total_usuarios}} usuarios</span>
                </div>
            </div>
        </div>
        @endcan

        {{-- 🔹 NUEVA SECCIÓN DE PRODUCTOS --}}
        @can('admin.productos.index')
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box zoomP">
                <a href="{{url('/admin/productos')}}" class="info-box-icon bg-success ">
                    <img src="{{url('/img/producto.gif')}}" alt="Productos">
                </a>
                <div class="info-box-content">
                    <span class="info-box-text">Productos registrados</span>
                    <span class="info-box-number">{{$total_productos ?? 0}} productos</span>
                </div>
            </div>
        </div>
        @endcan

    </div>


    @if (Auth::user()->roles->pluck('name')->implode(', ') == 'ADMINISTRADOR')
    <div class="row">

        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Total de clientes por mes</h3>
                </div>
                <div class="card-body">
                    <div>
                        <canvas id="myChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Total de pedidos por mes</h3>
                </div>
                <div class="card-body">
                    <div>
                        <canvas id="myChart2"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Estado de los pedidos</h3>
                </div>
                <div class="card-body">
                    <div>
                        <canvas id="myChart3" style="height: 100px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>
    @endif
@stop

@section('css')
<style>
     .zoomP {
        transition: transform 0.3s ease;
    }
    .zoomP:hover {
        transform: scale(1.05);
    }
    .info-box-icon {
        width: 70px;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 2.5rem;
        color: rgba(255, 255, 255, 0.7);
    }
    .info-box img {
        max-width: 100%;
        height: auto;
    }
</style>
@stop

@section('js')
<script>
    var meses = @json($meses);
    var clientes = @json($clientes);
    new Chart(document.getElementById('myChart'), {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Total de clientes por mes',
                data: clientes,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });

    var meses = @json($meses_pedidos);
    var pedidos = @json($pedidos);
    new Chart(document.getElementById('myChart2'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Total de pedidos por mes',
                data: pedidos,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });

    var estado = ['Pendiente','En camino','Entregado']; 
    var cantidad = [{{ $total_pedidos_asignados }}, {{ $total_pedidos_en_camino }}, {{ $total_pedidos_entregados }}];
    new Chart(document.getElementById('myChart3'), {
        type: 'pie',
        data: {
            labels: estado,
            datasets: [{
                label: 'Cantidad de pedidos por estado',
                data: cantidad,
                backgroundColor: ['#f39c12', '#17a2b8', '#2ecc71'],
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        }
    });
</script>
@stop