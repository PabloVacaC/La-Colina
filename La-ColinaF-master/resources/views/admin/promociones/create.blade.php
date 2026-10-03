@extends('adminlte::page')

@section('content')
<div class="container-fluid">

    <h4 class="mb-3">Nueva promoción</h4>

    <form action="{{ route('admin.promociones.store') }}" method="POST">
        @csrf

        <div class="card">
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Nombre de la promoción</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Producto en promoción</label>
                    <select name="producto_id" class="form-select" required>
                        <option value="">-- Seleccionar producto --</option>
                        @foreach($productos as $producto)
                            <option value="{{ $producto->id }}">
                                {{ $producto->nombre }} ({{ $producto->precio }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Precio promoción</label>
                    <input type="number" step="0.01" name="precio_promo"
                           class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="fecha_inicio" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activa" value="1"
                           class="form-check-input" checked>
                    <label class="form-check-label">Activar promoción</label>
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
                    Cancelar
                </a>
                <button class="btn btn-success">
                    Guardar promoción
                </button>
            </div>
        </div>

    </form>

</div>
@endsection