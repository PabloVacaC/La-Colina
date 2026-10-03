@extends('adminlte::page')

@section('content')
<div class="container-fluid">

    <h4 class="mb-3">Promociones</h4>

    <a href="{{ route('admin.promociones.create') }}" class="btn btn-primary mb-3">
        + Nueva promoción
    </a>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Producto</th>
                        <th>Precio promo</th>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <th>Estado</th>
                        <th>Situación</th>
                        <th>Aplicado a todos los clientes antiguos?</th>
                        <th width="180">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promociones as $promo)
                        <tr>
                            <td>{{ $promo->id }}</td>
                            <td>{{ $promo->nombre }}</td>
                            <td>{{ $promo->producto->nombre ?? '—' }}</td>
                            <td>{{ number_format($promo->precio_promo, 2) }}</td>
                            <td>{{ optional($promo->fecha_inicio)->format('d/m/Y') }}</td>
                            <td>{{ optional($promo->fecha_fin)->format('d/m/Y') }}</td>
                            <td>
                                @if($promo->activa)
                                    <span class="badge bg-success">Activa</span>
                                @else
                                    <span class="badge bg-secondary">Inactiva</span>
                                @endif
                            </td>


                            <td class="text-center">
                                @if($promo->estaVencidaPorFecha())
                                    <span class="badge bg-danger">Vencida</span>
                                @else
                                    <span class="badge bg-success">Vigente</span>
                                @endif
                            </td>

                            
                            <td class="text-center">
                                @if($promo->aplicar_a_todos)
                                    <span class="badge bg-success">Sí</span>
                                @else
                                    <span class="badge bg-danger">No</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('admin.promociones.edit', $promo->id) }}"
                                   class="btn btn-sm btn-warning">
                                    Editar
                                </a>

                                <form action="{{ route('admin.promociones.toggle', $promo->id) }}"
                                      method="POST"
                                      class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-info">
                                        {{ $promo->activa ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>

                                <form action="{{ route('admin.promociones.destroy', $promo->id) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('¿Eliminar promoción?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">
                                No hay promociones registradas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection