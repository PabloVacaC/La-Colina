{{-- resources/views/admin/pedidos/ajax/reporte_motoquero_tabla.blade.php --}}

@if($resumen->count() === 0)

    <p class="text-center text-muted">
        No se registraron ventas en esta fecha.
    </p>

@else

    <table class="table table-bordered table-sm text-center">
        <thead class="table-dark">
            <tr>
                <th>Producto</th>
                <th>Método de pago</th>
                <th>Cantidad total</th>
                <th>Total (Bs)</th>
            </tr>
        </thead>

        <tbody>
            @foreach($resumen as $item)
                <tr>
                    <td>{{ $item['producto'] }}</td>
                    <td>{{ $item['metodo'] }}</td>
                    <td>{{ $item['cantidad'] }}</td>
                    <td>{{ number_format($item['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>

        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Total en EFECTIVO:</th>
                <th>Bs {{ number_format($total_efectivo, 2) }}</th>
            </tr>
            <tr>
                <th colspan="3" class="text-end">Total en QR:</th>
                <th>Bs {{ number_format($total_qr, 2) }}</th>
            </tr>
            <tr class="table-secondary">
                <th colspan="3" class="text-end">TOTAL GENERAL:</th>
                <th>Bs {{ number_format($total_general, 2) }}</th>
            </tr>
        </tfoot>
    </table>

@endif