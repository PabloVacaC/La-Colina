@extends('adminlte::page')
@section('content_header') <h1>Editar precios especiales — {{ $cliente->nombre }}</h1> @stop
@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.especiales.update', $cliente->id) }}" method="POST">
            @csrf @method('PUT')
            <h5>Productos</h5>
            <table class="table">
                <thead><tr><th>Usar</th><th>Producto</th><th>Precio especial (Bs)</th></tr></thead>
                <tbody>
                    @foreach($productos as $prod)
                    @php $valor = $precios[$prod->id] ?? ''; @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="toggle-precio" data-prod="{{ $prod->id }}" {{ $valor !== '' ? 'checked' : '' }}>
                        </td>
                        <td>{{ $prod->nombre }}</td>
                        <td>
                            <input type="number" step="0.01" name="precios[{{ $prod->id }}][precio]" class="form-control precio-input" data-prod="{{ $prod->id }}" {{ $valor !== '' ? '' : 'disabled' }} value="{{ $valor }}">
                            <input type="hidden" name="precios[{{ $prod->id }}][producto_id]" value="{{ $prod->id }}">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <button class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</div>

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
</script>
@stop

@stop