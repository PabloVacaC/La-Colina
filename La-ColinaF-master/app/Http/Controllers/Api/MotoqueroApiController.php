<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CierreVenta;
use App\Models\CierreVentaGasto;
use App\Models\Configuracion;
use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\Motoquero;
use App\Models\MotoqueroUbicacion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MotoqueroApiController extends Controller
{
    /**
     * Login para motoqueros / distribuidores
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas. Verifica tu correo y contraseña.',
            ], 401);
        }

        $motoquero = Motoquero::where('usuario_id', $user->id)->first();

        if (!$motoquero) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene perfil de distribuidor/motoquero asignado.',
            ], 403);
        }

        // Generar token
        $token = $user->createToken('motoquero_app_token')->plainTextToken;

        return response()->json([
            'success'   => true,
            'message'   => 'Inicio de sesión exitoso',
            'token'     => $token,
            'user'      => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'motoquero' => [
                'id'               => $motoquero->id,
                'nombres'          => $motoquero->nombres,
                'apellidos'        => $motoquero->apellidos,
                'ci'               => $motoquero->ci,
                'celular'          => $motoquero->celular,
                'direccion'        => $motoquero->direccion,
                'placa'            => $motoquero->placa,
                'fecha_nacimiento' => $motoquero->fecha_nacimiento,
            ],
        ]);
    }

    /**
     * Obtener pedidos del motoquero clasificados por estado
     */
    public function getPedidos($motoqueroId)
    {
        $motoquero = Motoquero::findOrFail($motoqueroId);

        $hoy = Carbon::today();

        $todosProductos = Producto::all();

        // Pedidos Asignados
        $asignadosRaw = Pedido::with(['cliente.preciosEspeciales', 'detalles'])
            ->where('motoquero_id', $motoqueroId)
            ->where('estado', 'Asignado')
            ->orderBy('orden', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Pedidos En Camino (activos)
        $enCaminoRaw = Pedido::with(['cliente.preciosEspeciales', 'detalles'])
            ->where('motoquero_id', $motoqueroId)
            ->where('estado', 'En camino')
            ->orderBy('orden', 'asc')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Pedidos Entregados de hoy
        $entregadosRaw = Pedido::with(['cliente.preciosEspeciales', 'detalles'])
            ->where('motoquero_id', $motoqueroId)
            ->where('estado', 'Entregado')
            ->whereDate('updated_at', $hoy)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Obtener última compra de cada cliente para pre-cargar en el formulario de entrega
        $todos = $asignadosRaw->concat($enCaminoRaw)->concat($entregadosRaw);
        $clienteIds = $todos->pluck('cliente_id')->filter()->unique();

        $ultimasComprasMap = collect();
        if ($clienteIds->isNotEmpty()) {
            $ultimosEntregados = Pedido::with('detalles')
                ->whereIn('cliente_id', $clienteIds)
                ->where('estado', 'Entregado')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('cliente_id')
                ->map(fn($group) => $group->first());

            foreach ($ultimosEntregados as $cid => $pedEnt) {
                if ($pedEnt && $pedEnt->detalles->isNotEmpty()) {
                    $ultimasComprasMap[$cid] = $pedEnt->detalles->map(function ($d) {
                        return [
                            'id'              => $d->id,
                            'producto'        => $d->producto,
                            'detalle'         => $d->detalle,
                            'cantidad'        => (int) $d->cantidad,
                            'precio_unitario' => (float) $d->precio_unitario,
                            'precio_total'    => (float) $d->precio_total,
                        ];
                    })->values()->toArray();
                }
            }
        }

        $format = fn($p) => $this->formatPedido($p, $ultimasComprasMap->get($p->cliente_id, []), $todosProductos);

        $asignados = $asignadosRaw->map($format);
        $enCamino = $enCaminoRaw->map($format);
        $entregados = $entregadosRaw->map($format);

        // Lista de productos del sistema para modal de entrega
        $productos = Producto::all()->map(function ($p) {
            return [
                'id'     => $p->id,
                'nombre' => $p->nombre,
                'precio' => (float) $p->precio,
            ];
        });

        return response()->json([
            'success'    => true,
            'motoquero'  => [
                'id'      => $motoquero->id,
                'nombres' => $motoquero->nombres . ' ' . $motoquero->apellidos,
                'placa'   => $motoquero->placa,
            ],
            'productos'  => $productos,
            'asignados'  => $asignados,
            'en_camino'  => $enCamino,
            'entregados' => $entregados,
        ]);
    }

    /**
     * Obtener listado de productos disponibles
     */
    public function getProductos()
    {
        return response()->json([
            'success'   => true,
            'productos' => Producto::all(['id', 'nombre', 'precio']),
        ]);
    }

    /**
     * Aceptar / Tomar un pedido
     */
    public function tomarPedido($id)
    {
        $pedido = Pedido::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Pedido en atención.',
            'pedido'  => $this->formatPedido($pedido->fresh(['cliente.preciosEspeciales', 'detalles'])),
        ]);
    }

    /**
     * Rechazar pedido
     */
    public function rechazarPedido($id)
    {
        $pedido = Pedido::findOrFail($id);
        $pedido->estado = 'Pendiente';
        $pedido->motoquero_id = null;
        $pedido->orden = 0;
        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Pedido rechazado y devuelto a pendientes.',
        ]);
    }

    /**
     * Cancelar entrega de un pedido (cliente no sale, no atiende o no se encuentra)
     * Desasigna el pedido del repartidor y lo regresa a estado 'Pendiente' con orden 0,
     * permitiendo que desaparezca de la ruta activa del repartidor y este continúe a la siguiente ubicación.
     */
    public function cancelarPedido(Request $request, $id)
    {
        $pedido = Pedido::findOrFail($id);

        if ($pedido->estado === 'Entregado') {
            return response()->json([
                'success' => false,
                'message' => 'Un pedido ya entregado no puede ser cancelado.',
            ], 422);
        }

        $pedido->estado = 'Pendiente';
        $pedido->motoquero_id = null;
        $pedido->orden = 0;
        $pedido->save();

        Log::info("Entrega del pedido #{$pedido->id} cancelada por el distribuidor (cliente no sale). Devuelto a pendientes.");

        return response()->json([
            'success'   => true,
            'message'   => 'Entrega cancelada correctamente. El pedido ha sido retirado de tu ruta y devuelto a pendientes.',
            'pedido_id' => $pedido->id,
        ]);
    }

    /**
     * Tomar todos los pedidos de una ruta específica
     * Mantiene los pedidos en 'Asignado' para que sigan visibles y ordenados en el panel del admin
     * hasta que sean entregados efectivamente.
     */
    public function tomarRuta(Request $request, $id)
    {
        $ruta = $request->input('ruta');

        return response()->json([
            'success'              => true,
            'message'              => "Ruta $ruta iniciada.",
            'pedidos_actualizados' => 0,
        ]);
    }

    /**
     * Restaurar pedidos de 'En camino' de regreso a 'Asignado' para que permanezcan en la lista del admin
     */
    public function restaurarAsignados($id)
    {
        $hoy = Carbon::today();
        $cant = Pedido::where('motoquero_id', $id)
            ->where('estado', 'En camino')
            ->whereDate('created_at', $hoy)
            ->update(['estado' => 'Asignado']);

        return response()->json([
            'success'              => true,
            'message'              => "$cant pedidos restaurados a Asignado.",
            'pedidos_actualizados' => $cant,
        ]);
    }

    /**
     * Finalizar / Entregar pedido con desglose de productos y método de pago
     */
    public function finalizarPedido(Request $request, $id)
    {
        $request->validate([
            'metodo_pago' => 'nullable|in:Efectivo,QR,efectivo,qr',
        ]);

        DB::beginTransaction();

        try {
            $pedido = Pedido::with('cliente')->findOrFail($id);

            // Protección: Si el pedido ya fue entregado, el distribuidor no puede modificarlo nuevamente
            if ($pedido->estado === 'Entregado') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido ya fue entregado y no se puede modificar nuevamente.',
                ], 422);
            }

            $pedido->estado = 'Entregado';

            if ($request->filled('metodo_pago')) {
                $pedido->metodo_pago = ucfirst(strtolower($request->metodo_pago));
            }
            if ($request->filled('qr_pago_estado')) {
                $pedido->qr_pago_estado = $request->qr_pago_estado;
            }

            // Si se enviaron items / productos ajustados
            if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
                $cliente = $pedido->cliente;
                DetallePedido::where('pedido_id', $pedido->id)->delete();
                $total = 0;

                foreach ($request->items as $item) {
                    $prodId = $item['producto_id'] ?? null;
                    $cant = (float) ($item['cantidad'] ?? 1);
                    $producto = Producto::find($prodId);
                    if (!$producto) continue;

                    $precioUnitario = isset($item['precio_unitario']) && $item['precio_unitario'] !== null
                        ? (float) $item['precio_unitario']
                        : ($cliente ? (float) $cliente->getPrecioProducto($producto) : (float) $producto->precio);

                    $subtotal = $precioUnitario * $cant;
                    $total += $subtotal;

                    DetallePedido::create([
                        'pedido_id'       => $pedido->id,
                        'producto'        => $producto->nombre,
                        'detalle'         => '',
                        'cantidad'        => $cant,
                        'precio_unitario' => $precioUnitario,
                        'precio_total'    => $subtotal,
                    ]);
                }

                $pedido->total_precio = $total;
            }

            $pedido->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '¡Pedido entregado con éxito!',
                'pedido'  => $this->formatPedido($pedido->fresh(['cliente.preciosEspeciales', 'detalles'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al finalizar pedido #$id vía API: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al registrar la entrega. Los datos del pedido no fueron alterados.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir foto de la casa o comprobante de entrega
     */
    public function subirFotoCasa(Request $request, $clienteId)
    {
        $request->validate([
            'imagen_casa' => 'required|image|max:10240', // Hasta 10MB
        ]);

        $cliente = Cliente::findOrFail($clienteId);

        if ($cliente->imagen_casa) {
            Storage::disk('public')->delete($cliente->imagen_casa);
        }

        $ruta = $request->file('imagen_casa')->store('clientes', 'public');
        $cliente->imagen_casa = $ruta;
        $cliente->save();

        return response()->json([
            'success'    => true,
            'message'    => 'Foto subida correctamente.',
            'imagen_url' => asset('storage/' . $ruta),
        ]);
    }

    /**
     * Guardar ubicación GPS del motoquero
     */
    public function guardarUbicacion(Request $request)
    {
        $request->validate([
            'motoquero_id' => 'required|exists:motoqueros,id',
            'latitud'      => 'required|numeric',
            'longitud'     => 'required|numeric',
        ]);

        $ubicacion = MotoqueroUbicacion::updateOrCreate(
            ['motoquero_id' => $request->motoquero_id],
            [
                'latitud'       => $request->latitud,
                'longitud'      => $request->longitud,
                'estado'        => 'activo',
                'registrado_en' => now(),
            ]
        );

        return response()->json([
            'success'   => true,
            'id'        => $ubicacion->id,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Obtener ubicaciones en tiempo real de todos los motoqueros para el Admin (Optimizado)
     */
    public function getUbicacionesAdmin()
    {
        $motoqueros = Motoquero::with(['usuario'])->get();
        if ($motoqueros->isEmpty()) {
            return response()->json([
                'success'      => true,
                'motoqueros'   => [],
                'servidor_hora'=> now()->format('H:i:s'),
            ]);
        }

        $ids = $motoqueros->pluck('id');

        // 1. Ubicaciones actuales (1 fila por motoquero)
        $ubicaciones = MotoqueroUbicacion::whereIn('motoquero_id', $ids)->get()->keyBy('motoquero_id');

        // 2. Pedidos en camino actuales (1 sola consulta para todos)
        $pedidosEnCamino = Pedido::with('cliente')
            ->whereIn('motoquero_id', $ids)
            ->where('estado', 'En camino')
            ->get()
            ->keyBy('motoquero_id');

        // 3. Conteo de pedidos pendientes asignados (1 consulta agrupada)
        $pedidosAsignadosCount = Pedido::whereIn('motoquero_id', $ids)
            ->where('estado', 'Asignado')
            ->groupBy('motoquero_id')
            ->selectRaw('motoquero_id, count(*) as total')
            ->pluck('total', 'motoquero_id');

        // 4. Conteo de pedidos entregados hoy (1 consulta agrupada)
        $pedidosEntregadosHoy = Pedido::whereIn('motoquero_id', $ids)
            ->where('estado', 'Entregado')
            ->whereDate('updated_at', Carbon::today())
            ->groupBy('motoquero_id')
            ->selectRaw('motoquero_id, count(*) as total')
            ->pluck('total', 'motoquero_id');

        $datos = [];
        foreach ($motoqueros as $m) {
            $ultima = $ubicaciones->get($m->id);
            $pedidoActual = $pedidosEnCamino->get($m->id);
            $cantPendientes = $pedidosAsignadosCount->get($m->id, 0);
            $cantEntregados = $pedidosEntregadosHoy->get($m->id, 0);

            $datos[] = [
                'motoquero_id'        => $m->id,
                'nombre'              => $m->nombres . ' ' . $m->apellidos,
                'celular'             => $m->celular,
                'placa'               => $m->placa ?? 'S/P',
                'latitud'             => $ultima ? (float) $ultima->latitud : null,
                'longitud'            => $ultima ? (float) $ultima->longitud : null,
                'registrado_en'       => $ultima ? $ultima->registrado_en->diffForHumans() : 'Sin registro',
                'registrado_en_iso'   => $ultima ? $ultima->registrado_en->toIso8601String() : null,
                'pedido_actual'       => $pedidoActual ? [
                    'id'               => $pedidoActual->id,
                    'orden'            => $pedidoActual->orden,
                    'ruta'             => $pedidoActual->ruta,
                    'cliente_nombre'   => $pedidoActual->cliente->nombre ?? 'Sin nombre',
                    'cliente_telefono' => $pedidoActual->cliente->celular ?? '',
                    'cliente_direccion'=> $pedidoActual->cliente->direccion ?? '',
                    'cliente_lat'      => $pedidoActual->cliente->latitud ?? null,
                    'cliente_lng'      => $pedidoActual->cliente->longitud ?? null,
                    'total_precio'     => $pedidoActual->total_precio,
                ] : null,
                'pedidos_pendientes'  => $cantPendientes,
                'pedidos_entregados'  => $cantEntregados,
                'online'              => $ultima && $ultima->registrado_en->diffInMinutes(now()) <= 15,
            ];
        }

        return response()->json([
            'success'      => true,
            'motoqueros'   => $datos,
            'servidor_hora'=> now()->format('H:i:s'),
        ]);
    }

    /**
     * Formateador auxiliar para pedidos con soporte completo de descuentos y precios especiales
     */
    private function formatPedido(Pedido $p, ?array $ultimaCompra = null, $todosProductos = null): array
    {
        $cliente = $p->cliente;
        if ($todosProductos === null) {
            $todosProductos = Producto::all();
        }

        $imagenCasaUrl = null;
        if ($cliente && $cliente->imagen_casa) {
            $imagenCasaUrl = asset('storage/' . $cliente->imagen_casa);
        }

        // 1. Mapeo de precios con descuento / precios especiales para este cliente
        $preciosProductos = [];
        $tieneDescuento = false;
        $tipoDescuento = null;

        if ($cliente) {
            $promoActiva = $cliente->promoVigente();
            $preciosEsp = $cliente->preciosEspeciales;

            if ($promoActiva) {
                $tieneDescuento = true;
                $tipoDescuento = 'Promoción activa';
            } elseif ($preciosEsp && $preciosEsp->isNotEmpty()) {
                $tieneDescuento = true;
                $tipoDescuento = 'Precio especial';
            }

            foreach ($todosProductos as $prod) {
                $precioEfectivo = (float) $cliente->getPrecioProducto($prod);
                $preciosProductos[(int)$prod->id] = $precioEfectivo;
                if (!$tieneDescuento && $precioEfectivo < (float)$prod->precio) {
                    $tieneDescuento = true;
                    $tipoDescuento = 'Descuento cliente';
                }
            }
        } else {
            foreach ($todosProductos as $prod) {
                $preciosProductos[(int)$prod->id] = (float) $prod->precio;
            }
        }

        // 2. Si no se proporcionó $ultimaCompra pero el pedido tiene cliente, buscarla
        if ($ultimaCompra === null && $p->cliente_id) {
            $ultimo = Pedido::with('detalles')
                ->where('cliente_id', $p->cliente_id)
                ->where('estado', 'Entregado')
                ->where('id', '!=', $p->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($ultimo && $ultimo->detalles->isNotEmpty()) {
                $ultimaCompra = $ultimo->detalles->map(function ($d) {
                    return [
                        'id'              => $d->id,
                        'producto'        => $d->producto,
                        'detalle'         => $d->detalle,
                        'cantidad'        => (int) $d->cantidad,
                        'precio_unitario' => (float) $d->precio_unitario,
                        'precio_total'    => (float) $d->precio_total,
                    ];
                })->values()->toArray();
            } else {
                $ultimaCompra = [];
            }
        }

        // 3. Ajustar última compra con precios con descuento vigentes del cliente
        $ultimaCompraConDescuento = [];
        if (!empty($ultimaCompra)) {
            foreach ($ultimaCompra as $uc) {
                $nombreProd = trim($uc['producto'] ?? '');
                $prodMatch = $todosProductos->first(function ($pr) use ($nombreProd) {
                    return strcasecmp(trim($pr->nombre), $nombreProd) === 0;
                });
                $precioUnit = ($prodMatch && isset($preciosProductos[$prodMatch->id]))
                    ? $preciosProductos[$prodMatch->id]
                    : (float) ($uc['precio_unitario'] ?? 0);
                $cant = (int) ($uc['cantidad'] ?? 1);
                $ultimaCompraConDescuento[] = [
                    'id'              => $uc['id'] ?? null,
                    'producto'        => $uc['producto'],
                    'detalle'         => $uc['detalle'] ?? '',
                    'cantidad'        => $cant,
                    'precio_unitario' => $precioUnit,
                    'precio_total'    => (float) ($precioUnit * $cant),
                ];
            }
        }

        // 4. Estimación del total para pedidos nuevos/asignados sin detalle aún
        $totalEstimado = 0.0;
        if ($p->detalles->isNotEmpty()) {
            $totalEstimado = (float) $p->detalles->sum('precio_total');
        } elseif (!empty($ultimaCompraConDescuento)) {
            foreach ($ultimaCompraConDescuento as $item) {
                $totalEstimado += (float) ($item['precio_total'] ?? 0);
            }
        } else {
            // Cliente nuevo sin compra previa: estimar con 1 botellón de agua regular (o primer producto)
            $primerProd = $todosProductos->first();
            if ($primerProd) {
                $totalEstimado = isset($preciosProductos[$primerProd->id])
                    ? $preciosProductos[$primerProd->id]
                    : (float) $primerProd->precio;
            }
        }

        $totalPrecioFinal = (float) $p->total_precio;
        if ($totalPrecioFinal <= 0 && $p->estado !== 'Entregado') {
            $totalPrecioFinal = $totalEstimado;
        }

        return [
            'id'                            => $p->id,
            'estado'                        => $p->estado,
            'total_precio'                  => $totalPrecioFinal,
            'total_estimado'                => $totalEstimado,
            'tiene_descuento'               => $tieneDescuento,
            'tipo_descuento'                => $tipoDescuento,
            'precios_productos'             => $preciosProductos,
            'metodo_pago'                   => $p->metodo_pago,
            'qr_pago_estado'                => $p->qr_pago_estado,
            'orden'                         => $p->orden,
            'ruta'                          => $p->ruta,
            'emergencia'                    => (bool) $p->emergencia,
            'inicio_navegacion_este_pedido' => (bool) $p->inicio_navegacion_este_pedido,
            'created_at'                    => $p->created_at ? $p->created_at->format('d/m/Y H:i') : null,
            'cliente'                       => $cliente ? [
                'id'                 => $cliente->id,
                'nombre'             => $cliente->nombre,
                'celular'            => $cliente->celular_real ?? $cliente->celular,
                'referencia_celular' => $cliente->referencia_celular,
                'direccion'          => $cliente->direccion,
                'latitud'            => $cliente->latitud ? (float) $cliente->latitud : null,
                'longitud'           => $cliente->longitud ? (float) $cliente->longitud : null,
                'ubicacion_gps'      => $cliente->ubicacion_gps,
                'imagen_casa'        => $cliente->imagen_casa,
                'imagen_casa_url'    => $imagenCasaUrl,
            ] : null,
            'detalles'                      => $p->detalles->map(function ($d) {
                return [
                    'id'              => $d->id,
                    'producto'        => $d->producto,
                    'detalle'         => $d->detalle,
                    'cantidad'        => (int) $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'precio_total'    => (float) $d->precio_total,
                ];
            }),
            'ultima_compra'                 => !empty($ultimaCompraConDescuento) ? $ultimaCompraConDescuento : ($ultimaCompra ?? []),
        ];
    }

    /**
     * Obtener el cierre del día (si ya existe) y los totales calculados
     */
    public function getCierreDia($id, Request $request)
    {
        $fecha = $request->fecha ?? Carbon::today()->toDateString();
        $inicio = Carbon::parse($fecha)->startOfDay();
        $fin    = Carbon::parse($fecha)->endOfDay();

        $motoquero = Motoquero::find($id);
        if (!$motoquero) {
            return response()->json(['success' => false, 'message' => 'Motoquero no encontrado.'], 404);
        }

        $pedidos = Pedido::with('detalles')
            ->where('motoquero_id', $id)
            ->where('estado', 'Entregado')
            ->whereBetween('updated_at', [$inicio, $fin])
            ->get();

        $ingresoBruto = (float) $pedidos->sum('total_precio');
        $ingresoEfectivo = (float) $pedidos->where('metodo_pago', 'Efectivo')->sum('total_precio');
        $ingresoQR = (float) $pedidos->where('metodo_pago', 'QR')->sum('total_precio');

        $vendidosRegular = 0;
        $vendidosAlcalina = 0;
        $vendidosEnteroRegular = 0;
        $vendidosEnteroAlcalina = 0;
        $vendidosDispensers = 0;

        foreach ($pedidos as $p) {
            foreach ($p->detalles as $det) {
                $nom = strtolower(trim($det->producto));
                if ($nom === 'agua regular') {
                    $vendidosRegular += (int)$det->cantidad;
                } elseif ($nom === 'agua alcalina') {
                    $vendidosAlcalina += (int)$det->cantidad;
                } elseif (str_contains($nom, 'regular') && (str_contains($nom, 'entero') || str_contains($nom, 'botell'))) {
                    $vendidosEnteroRegular += (int)$det->cantidad;
                } elseif (str_contains($nom, 'alcalina') && (str_contains($nom, 'entero') || str_contains($nom, 'botell'))) {
                    $vendidosEnteroAlcalina += (int)$det->cantidad;
                } elseif (str_contains($nom, 'dispensador') || str_contains($nom, 'bomba') || str_contains($nom, 'bombita')) {
                    $vendidosDispensers += (int)$det->cantidad;
                }
            }
        }

        $cierreExistente = CierreVenta::with('gastos')
            ->where('fecha', $fecha)
            ->where('motoquero_id', $id)
            ->first();

        $configuracion = Configuracion::first();
        $telefonoEmpresa = $configuracion->telefono ?? '59163524474';

        return response()->json([
            'success' => true,
            'fecha' => $fecha,
            'motoquero' => [
                'id' => $motoquero->id,
                'nombre' => $motoquero->nombres . ' ' . $motoquero->apellidos,
            ],
            'telefono_empresa' => $telefonoEmpresa,
            'totales' => [
                'pedidos_entregados' => $pedidos->count(),
                'ingreso_bruto' => $ingresoBruto,
                'ingreso_efectivo' => $ingresoEfectivo,
                'ingreso_qr' => $ingresoQR,
                'botellones_normales' => $vendidosRegular,
                'alcalinas' => $vendidosAlcalina,
                'enteros_normales' => $vendidosEnteroRegular,
                'enteros_alcalinas' => $vendidosEnteroAlcalina,
                'dispensers' => $vendidosDispensers,
            ],
            'cierre_existente' => $cierreExistente ? [
                'id' => $cierreExistente->id,
                'total_gastos' => (float)$cierreExistente->total_gastos_distribucion,
                'efectivo_entregado' => (float)$cierreExistente->efectivo_entregado,
                'gastos' => $cierreExistente->gastos->map(fn($g) => [
                    'concepto' => $g->concepto,
                    'monto' => (float)$g->monto,
                ]),
            ] : null,
        ]);
    }

    /**
     * Finalizar día con registro de gastos (Combustibles, Otros) y cálculo de efectivo a entregar
     */
    public function finalizarDia($id, Request $request)
    {
        $request->validate([
            'fecha' => 'nullable|date',
            'gastos' => 'nullable|array',
            'gastos.*.concepto' => 'required_with:gastos|string',
            'gastos.*.monto' => 'required_with:gastos|numeric|min:0',
        ]);

        $fecha = $request->fecha ?? Carbon::today()->toDateString();
        $inicio = Carbon::parse($fecha)->startOfDay();
        $fin    = Carbon::parse($fecha)->endOfDay();

        $motoquero = Motoquero::find($id);
        if (!$motoquero) {
            return response()->json(['success' => false, 'message' => 'Motoquero no encontrado.'], 404);
        }

        $pedidos = Pedido::with('detalles')
            ->where('motoquero_id', $id)
            ->where('estado', 'Entregado')
            ->whereBetween('updated_at', [$inicio, $fin])
            ->get();

        $ingresoBruto = (float) $pedidos->sum('total_precio');
        $ingresoEfectivo = (float) $pedidos->where('metodo_pago', 'Efectivo')->sum('total_precio');
        $ingresoQR = (float) $pedidos->where('metodo_pago', 'QR')->sum('total_precio');

        $vendidosRegular = 0;
        $vendidosAlcalina = 0;
        $vendidosEnteroRegular = 0;
        $vendidosEnteroAlcalina = 0;
        $vendidosDispensers = 0;

        foreach ($pedidos as $p) {
            foreach ($p->detalles as $det) {
                $nom = strtolower(trim($det->producto));
                if ($nom === 'agua regular') {
                    $vendidosRegular += (int)$det->cantidad;
                } elseif ($nom === 'agua alcalina') {
                    $vendidosAlcalina += (int)$det->cantidad;
                } elseif (str_contains($nom, 'regular') && (str_contains($nom, 'entero') || str_contains($nom, 'botell'))) {
                    $vendidosEnteroRegular += (int)$det->cantidad;
                } elseif (str_contains($nom, 'alcalina') && (str_contains($nom, 'entero') || str_contains($nom, 'botell'))) {
                    $vendidosEnteroAlcalina += (int)$det->cantidad;
                } elseif (str_contains($nom, 'dispensador') || str_contains($nom, 'bomba') || str_contains($nom, 'bombita')) {
                    $vendidosDispensers += (int)$det->cantidad;
                }
            }
        }

        $totalGastos = collect($request->gastos)->sum(fn($g) => (float)$g['monto']);
        $efectivoEntregado = max(0, $ingresoEfectivo - $totalGastos);

        $cierre = DB::transaction(function () use ($fecha, $id, $ingresoBruto, $ingresoEfectivo, $ingresoQR, $totalGastos, $efectivoEntregado, $request) {
            $c = CierreVenta::updateOrCreate(
                [
                    'fecha' => $fecha,
                    'motoquero_id' => $id,
                ],
                [
                    'ingreso_bruto' => $ingresoBruto,
                    'ingreso_efectivo' => $ingresoEfectivo,
                    'ingreso_qr' => $ingresoQR,
                    'total_gastos_distribucion' => $totalGastos,
                    'efectivo_entregado' => $efectivoEntregado,
                ]
            );

            $c->gastos()->delete();
            foreach ($request->gastos ?? [] as $g) {
                CierreVentaGasto::create([
                    'cierre_venta_id' => $c->id,
                    'concepto' => $g['concepto'],
                    'monto' => $g['monto'],
                ]);
            }

            return $c;
        });

        $configuracion = Configuracion::first();
        $telefonoEmpresa = $configuracion->telefono ?? '59163524474';
        $nombreEmpresa = $configuracion->nombre ?? 'La Colina';

        // Generar texto para WhatsApp
        $fechaFmt = Carbon::parse($fecha)->format('d/m/Y');
        $gastosTexto = '';
        if (!empty($request->gastos)) {
            foreach ($request->gastos as $g) {
                $gastosTexto .= "• " . $g['concepto'] . ": Bs. " . number_format($g['monto'], 2) . "\n";
            }
        } else {
            $gastosTexto = "• Sin gastos registrados\n";
        }

        $reporteTexto = "📋 *CIERRE DE VENTAS DEL DÍA*\n"
            . "📅 *Fecha:* {$fechaFmt}\n"
            . "🛵 *Distribuidor:* {$motoquero->nombres} {$motoquero->apellidos}\n"
            . "🏢 *Empresa:* {$nombreEmpresa}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📦 *PRODUCTOS VENDIDOS:*\n"
            . "• Botellones normales: {$vendidosRegular}\n"
            . "• Alcalinas: {$vendidosAlcalina}\n"
            . "• Enteros normales: {$vendidosEnteroRegular}\n"
            . "• Enteros alcalinas: {$vendidosEnteroAlcalina}\n"
            . "• Dispensers: {$vendidosDispensers}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "💰 *RESUMEN DE INGRESOS:*\n"
            . "💵 Total Efectivo: Bs. " . number_format($ingresoEfectivo, 2) . "\n"
            . "📱 Total en QR: Bs. " . number_format($ingresoQR, 2) . "\n"
            . "💎 Total Ingresos: Bs. " . number_format($ingresoBruto, 2) . "\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "⛽ *GASTOS REGISTRADOS:*\n"
            . $gastosTexto
            . "🔻 Total Gastos: Bs. " . number_format($totalGastos, 2) . "\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "💵 *TOTAL A ENTREGAR EN EFECTIVO:*\n"
            . "👉 *Bs. " . number_format($efectivoEntregado, 2) . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━";

        return response()->json([
            'success' => true,
            'message' => 'Cierre del día finalizado correctamente.',
            'data' => [
                'cierre_id' => $cierre->id,
                'fecha' => $fecha,
                'ingreso_bruto' => $ingresoBruto,
                'ingreso_efectivo' => $ingresoEfectivo,
                'ingreso_qr' => $ingresoQR,
                'total_gastos' => $totalGastos,
                'efectivo_entregado' => $efectivoEntregado,
                'productos' => [
                    'botellones_normales' => $vendidosRegular,
                    'alcalinas' => $vendidosAlcalina,
                    'enteros_normales' => $vendidosEnteroRegular,
                    'enteros_alcalinas' => $vendidosEnteroAlcalina,
                    'dispensers' => $vendidosDispensers,
                ],
                'gastos' => $cierre->gastos()->get(['concepto', 'monto']),
                'reporte_texto' => $reporteTexto,
                'whatsapp_url' => 'https://wa.me/' . $telefonoEmpresa . '?text=' . urlencode($reporteTexto),
            ]
        ]);
    }
}
