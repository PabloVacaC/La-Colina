@extends('adminlte::page')

@section('content_header')
    <h1>Registro de descuentos</h1>
@stop

@section('content')
<div class="card">

    {{-- HEADER --}}
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <b>Clientes con precio especial</b>
        </div>
        <div>
            <a href="{{ route('admin.especiales.create') }}"
               class="btn btn-primary btn-sm">
                Crear cliente con precio especial
            </a>
        </div>
    </div>

    {{-- BODY --}}
    <div class="card-body">

        {{-- MENSAJE --}}
        @if(session('mensaje'))
            <div class="alert alert-success">
                {{ session('mensaje') }}
            </div>
        @endif

        {{-- BUSCADOR --}}
        <div class="mb-3">
            <input
                type="text"
                id="buscadorClientes"
                class="form-control"
                placeholder="Buscar cliente..."
            >
        </div>

        {{-- TABLA --}}
        <div class="table-responsive">
            <table class="table table-sm" id="tablaClientes">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th># Precios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clientes as $c)
                    <tr>
                        <td>{{ $c->nombre }}</td>
                        <td>{{ $c->precios_especiales_count }}</td>
                        <td>
                            <a href="{{ route('admin.especiales.edit', $c->id) }}"
                               class="btn btn-sm btn-warning">
                                Editar
                            </a>

                            <form action="{{ route('admin.especiales.destroy', $c->id) }}"
                                  method="POST"
                                  style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger"
                                        onclick="return confirm('¿Eliminar precios especiales?')">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</div>
@stop

{{-- ===================== --}}
{{-- CSS --}}
{{-- ===================== --}}
@section('css')
<style>
/* Buscador un poco más alto */
#buscadorClientes {
    height: 45px;
    font-size: 15px;
}

/* Opcional: hover suave */
#tablaClientes tbody tr:hover {
    background-color: #f5f5f5;
}
</style>
@stop

{{-- ===================== --}}
{{-- JS --}}
{{-- ===================== --}}
@section('js')
<script>
document.getElementById('buscadorClientes').addEventListener('keyup', function () {

    const texto = this.value.toLowerCase();
    const filas = document.querySelectorAll('#tablaClientes tbody tr');

    filas.forEach(fila => {
        const nombreCliente = fila.children[0].innerText.toLowerCase();

        if (nombreCliente.includes(texto)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
});
</script>
@stop