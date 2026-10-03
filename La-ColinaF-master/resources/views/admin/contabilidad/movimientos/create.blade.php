@extends('adminlte::page')

@section('title', 'Registrar movimiento contable')

@section('content')
<div class="container-fluid">

    <h4 class="mb-4">Registrar movimiento contable</h4>

    {{-- ================= FORMULARIO PRINCIPAL (NO SE TOCA) ================= --}}
    <form method="POST" action="{{ route('admin.contabilidad.movimientos.store') }}" class="card mb-4">
        @csrf

        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-control" required>
                    <option value="">— Seleccionar —</option>
                    <option value="gasto">Gasto</option>
                    <option value="ingreso">Ingreso</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-control" required>
                    <option value="">— Seleccionar —</option>
                    <option value="distribucion">Distribución</option>
                    <option value="produccion">Producción</option>
                    <option value="administrativo">Administrativo</option>
                    <option value="reinversion">Reinversión</option>
                    <option value="otro_ingreso">Otro ingreso</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Subcategoría</label>
                <input type="text" name="subcategoria" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" class="form-control"
                       value="{{ now()->toDateString() }}" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Monto (Bs)</label>
                <input type="number" step="0.01" name="monto"
                       class="form-control" required>
            </div>

            <div class="col-md-9">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion"
                       class="form-control">
            </div>
        </div>

        <div class="card-footer text-end">
            <button class="btn btn-success">Guardar movimiento</button>
        </div>
    </form>

    {{-- ================= GASTOS FIJOS ================= --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div>
                <h5 class="mb-0">Gastos fijos mensuales</h5>
                <small class="text-muted">Sueldos, deudas, servicios</small>
            </div>

            <button class="btn btn-sm btn-success"
                    data-toggle="modal"
                    data-target="#modalGastoFijo"
                    onclick="nuevoGastoFijo()">
                + Agregar gasto fijo
            </button>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                <tr>
                    <th>Categoría</th>
                    <th>Subcategoría</th>
                    <th>Concepto</th>
                    <th class="text-end">Monto</th>
                    <th>Día</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                    <th class="text-center">Registrado?</th>
                    <th class="text-center">Datos</th>
                </tr>
                </thead>
                <tbody>

                @forelse($gastosFijos as $gasto)
                    <tr>
                        <td>{{ ucfirst($gasto->categoria) }}</td>
                        <td>{{ $gasto->subcategoria }}</td>
                        <td>{{ $gasto->concepto }}</td>
                        <td class="text-end">{{ number_format($gasto->monto,2) }}</td>
                        <td>{{ $gasto->dia_referencia }}</td>

                        <td class="text-center">
                            <span class="badge bg-{{ $gasto->activo ? 'success' : 'secondary' }}">
                                {{ $gasto->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <button class="btn btn-sm btn-warning"
                                    data-toggle="modal"
                                    data-target="#modalGastoFijo"
                                    onclick='editarGastoFijo(@json($gasto))'>
                                ✏️
                            </button>

                            <form method="POST"
                                  action="{{ route('admin.contabilidad.gastos-fijos.toggle', $gasto->id) }}"
                                  class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm {{ $gasto->activo ? 'btn-danger' : 'btn-success' }}">
                                    {{ $gasto->activo ? 'Off' : 'On' }}
                                </button>
                            </form>
                        </td>


                       <td class="text-center">
                            @if($gasto->ya_registrado)
                                <span class="badge bg-success">
                                    Registrado este mes
                                </span>
                            @elseif(!$gasto->activo)
                                <span class="badge bg-secondary">
                                    Inactivo
                                </span>
                            @else
                                <span class="badge bg-warning text-dark">
                                    Pendiente
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            @if($gasto->activo && !$gasto->ya_registrado)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info"
                                    onclick='cargarGastoFijoEnFormulario(@json($gasto))'>
                                    ⬇️ Cargar
                                </button>
                            @else
                                <button class="btn btn-sm btn-secondary" disabled>
                                    ⬆️ No cargar
                                </button>
                            @endif
                        </td>


                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            No hay gastos fijos registrados
                        </td>
                    </tr>
                @endforelse

                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ================= MODAL GASTO FIJO ================= --}}
<div class="modal fade" id="modalGastoFijo" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="formGastoFijo">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="tituloModal">Gasto fijo</h5>
                    <button class="btn-close" data-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label>Categoría</label>
                        <select name="categoria" id="gf_categoria" class="form-control" required>
                            <option value="administrativo">Administrativo</option>
                            <option value="produccion">Producción</option>
                            <option value="distribucion">Distribución</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Subcategoría</label>
                        <input type="text" name="subcategoria" id="gf_subcategoria"
                               class="form-control" required>
                    </div>

                    <div class="col-md-12">
                        <label>Concepto</label>
                        <input type="text" name="concepto" id="gf_concepto"
                               class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label>Monto</label>
                        <input type="number" step="0.01" name="monto"
                               id="gf_monto" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label>Día referencia</label>
                        <input type="number" min="1" max="31"
                               name="dia_referencia"
                               id="gf_dia" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ================= JS ================= --}}
<script>
function nuevoGastoFijo() {
    document.getElementById('tituloModal').innerText = 'Agregar gasto fijo';
    document.getElementById('formGastoFijo').action =
        "{{ route('admin.contabilidad.gastos-fijos.store') }}";
    document.getElementById('formGastoFijo').reset();
}

function editarGastoFijo(g) {
    document.getElementById('tituloModal').innerText = 'Editar gasto fijo';
    document.getElementById('formGastoFijo').action =
        "/admin/contabilidad/gastos-fijos/" + g.id;

    document.getElementById('gf_categoria').value = g.categoria;
    document.getElementById('gf_subcategoria').value = g.subcategoria;
    document.getElementById('gf_concepto').value = g.concepto;
    document.getElementById('gf_monto').value = g.monto;
    document.getElementById('gf_dia').value = g.dia_referencia;
}
</script>

<script>
function cargarGastoFijoEnFormulario(g) {

    // Tipo siempre es gasto
    document.querySelector('select[name="tipo"]').value = 'gasto';

    // Categoría
    document.querySelector('select[name="categoria"]').value = g.categoria;

    // Subcategoría
    document.querySelector('input[name="subcategoria"]').value = g.subcategoria;

    // Monto
    document.querySelector('input[name="monto"]').value = g.monto;

    // Descripción = concepto del gasto fijo
    document.querySelector('input[name="descripcion"]').value = g.concepto;

    // Fecha → hoy (puedes cambiar esto luego)
    document.querySelector('input[name="fecha"]').value =
        new Date().toISOString().slice(0,10);

    // Scroll suave al formulario principal
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}
</script>
@endsection