<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Pedidos Asignados (por tomar/aceptar): Todos los pedidos pendientes asignados a este repartidor
        $asignados = Pedido::with(['cliente', 'detalles'])
            ->where('motoquero_id', $motoqueroId)
            ->whereIn('estado', ['Asignado', 'Por asignar'])
            ->orderBy('orden', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn($p) => $this->formatPedido($p));

        // Pedidos En Camino (activos): Todos los pedidos actualmente en curso de este repartidor
        $enCamino = Pedido::with(['cliente', 'detalles'])
            ->where('motoquero_id', $motoqueroId)
            ->where('estado', 'En camino')
            ->orderBy('orden', 'asc')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($p) => $this->formatPedido($p));

        // Pedidos Entregados de hoy
        $entregados = Pedido::with(['cliente', 'detalles'])
            ->where('motoquero_id', $motoqueroId)
            ->where('estado', 'Entregado')
            ->whereDate('updated_at', $hoy)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($p) => $this->formatPedido($p));

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
            'pedido'  => $this->formatPedido($pedido->fresh(['cliente', 'detalles'])),
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
        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Pedido rechazado y devuelto a pendientes.',
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
                'pedido'  => $this->formatPedido($pedido->fresh(['cliente', 'detalles'])),
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
            ->whereIn('estado', ['Asignado', 'Por asignar'])
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
     * Formateador auxiliar para pedidos
     */
    private function formatPedido(Pedido $p): array
    {
        $cliente = $p->cliente;

        $imagenCasaUrl = null;
        if ($cliente && $cliente->imagen_casa) {
            $imagenCasaUrl = asset('storage/' . $cliente->imagen_casa);
        }

        return [
            'id'                            => $p->id,
            'estado'                        => $p->estado,
            'total_precio'                  => (float) $p->total_precio,
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
                'celular'            => $cliente->celular,
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
        ];
    }
}
