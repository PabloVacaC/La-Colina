@extends('adminlte::page')
@section('content_header') <h1>Crear cliente con precio especial</h1> @stop
@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.especiales.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Cliente</label>
                <select name="cliente_id" class="form-control select2" required>
                    <option value="">Selecciona cliente</option>
                    @foreach($clientes as $cl)
                        <option value="{{ $cl->id }}">{{ $cl->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <hr>
            <h5>Productos (marca solo los que tengan precio especial)</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Usar</th><th>Producto</th><th>Precio normal</th><th>Precio especial (Bs)</th></tr></thead>
                    <tbody>
                        @foreach($productos as $prod)
                        <tr>
                            <td>
                                <input type="checkbox" class="toggle-precio" data-prod="{{ $prod->id }}">
                            </td>
                            <td>{{ $prod->nombre }}</td>
                            <td>{{ number_format($prod->precio ?? 0,2) }}</td>
                            <td>
                                <input type="number" step="0.01" name="precios[{{ $prod->id }}][precio]" class="form-control precio-input" data-prod="{{ $prod->id }}" disabled>
                                <input type="hidden" name="precios[{{ $prod->id }}][producto_id]" value="{{ $prod->id }}">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>


@section('css')
<style>
.select2-container--default .select2-selection--single {
    height: 45px;
    line-height: 45px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 45px;
}
</style>
@stop


@section('js')
<script>
document.querySelectorAll('.toggle-precio').forEach(chk => {
    chk.addEventListener('change', function() {
        const id = this.dataset.prod;
        const input = document.querySelector('.precio-input[data-prod="'+id+'"]');
        input.disabled = !this.checked;
        if(!this.checked) input.value = '';
    });
});


// 🔍 Activar buscador en clientes
$(document).ready(function () {
    $('.select2').select2({
        placeholder: 'Buscar cliente...',
        allowClear: true,
        width: '100%'
    });
});

</script>
@stop

@stop