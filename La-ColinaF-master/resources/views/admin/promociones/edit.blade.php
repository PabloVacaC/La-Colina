@extends('adminlte::page')

@section('content')
<div class="container-fluid">

    <h4 class="mb-3">Editar promoción</h4>

    <form action="{{ route('admin.promociones.update', $promocion->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre"
                           class="form-control"
                           value="{{ $promocion->nombre }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Producto</label>
                    <select name="producto_id" class="form-select" required>
                        @foreach($productos as $producto)
                            <option value="{{ $producto->id }}"
                                {{ $promocion->producto_id == $producto->id ? 'selected' : '' }}>
                                {{ $producto->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Precio promoción</label>
                    <input type="number" step="0.01"
                           name="precio_promo"
                           class="form-control"
                           value="{{ $promocion->precio_promo }}" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="fecha_inicio"
                               class="form-control"
                               value="{{ $promocion->fecha_inicio->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="fecha_fin"
                               class="form-control"
                               value="{{ $promocion->fecha_fin->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activa" value="1"
                           class="form-check-input"
                           {{ $promocion->activa ? 'checked' : '' }}>
                    <label class="form-check-label">Promoción activa</label>
                </div>


                <div class="form-check mb-3">
                    <input type="checkbox"
                        name="aplicar_a_todos"
                        value="1"
                        class="form-check-input"
                        {{ old('aplicar_a_todos', $promocion->aplicar_a_todos ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label">
                        Activar para todos los clientes ya registrados
                    </label>
                </div>

            </div>

            <div class="card-footer text-end">
                <a href="{{ route('admin.promociones.index') }}" class="btn btn-secondary">
                    Volver
                </a>
                <button class="btn btn-primary">
                    Actualizar
                </button>
            </div>
        </div>

    </form>

</div>
@endsection