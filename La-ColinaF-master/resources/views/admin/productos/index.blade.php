@extends('adminlte::page')

@section('title', 'Productos')

@section('content_header')
    <h1><b>Listado de Productos</b></h1>
    <hr>
@stop

@section('content')
    <a href="{{ route('admin.productos.create') }}" class="btn btn-primary mb-3">➕ Nuevo Producto</a>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Precio (Bs)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productos as $producto)
                        <tr>
                            <td>{{ $producto->id }}</td>
                            <td>{{ $producto->nombre }}</td>
                            <td>{{ number_format($producto->precio, 2) }}</td>
                            <td>
                                <a href="{{ route('admin.productos.edit', $producto->id) }}" class="btn btn-warning btn-sm">✏️ Editar</a>
                                <form action="{{ route('admin.productos.destroy', $producto->id) }}" method="POST"
                                    style="display:inline-block" onsubmit="return confirm('¿Eliminar este producto?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No hay productos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop