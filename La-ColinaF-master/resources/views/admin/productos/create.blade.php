@extends('adminlte::page')

@section('title', 'Nuevo Producto')

@section('content_header')
    <h1><b>Crear Nuevo Producto</b></h1>
    <hr>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.productos.store') }}" method="POST">
                @csrf

                <div class="form-group mb-3">
                    <label for="nombre">Nombre del Producto</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" required
                        placeholder="Ej: Agua 20L, Bidón, etc.">
                </div>

                <div class="form-group mb-3">
                    <label for="precio">Precio (Bs)</label>
                    <input type="number" step="0.01" name="precio" id="precio" class="form-control" required
                        placeholder="Ej: 10.00">
                </div>

                <button type="submit" class="btn btn-success">💾 Guardar Producto</button>
                <a href="{{ route('admin.productos.index') }}" class="btn btn-secondary">↩️ Volver</a>
            </form>
        </div>
    </div>
@stop