<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use App\Models\Producto;
use App\Models\Cliente;
use Illuminate\Http\Request;

class PromocionController extends Controller
{
    /* ============================
     * LISTAR PROMOS
     * ============================ */
    public function index()
    {
        $promociones = Promocion::with('producto')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.promociones.index', compact('promociones'));
    }

    /* ============================
     * FORM CREAR
     * ============================ */
    public function create()
    {
        $productos = Producto::orderBy('nombre')->get();

        return view('admin.promociones.create', compact('productos'));
    }

    /* ============================
     * GUARDAR PROMO
     * ============================ */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'       => 'required|string|max:255',
            'producto_id'  => 'required|integer|exists:productos,id',
            'precio_promo' => 'required|numeric|min:0',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'activa'       => 'nullable|boolean',
            'aplicar_a_todos'       => 'nullable|boolean',
        ]);

        $promocion = Promocion::create([
            'nombre'       => $request->nombre,
            'producto_id'  => $request->producto_id,
            'precio_promo' => $request->precio_promo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'activa'       => $request->boolean('activa'),
            'aplicar_a_todos'  => $request->has('aplicar_a_todos'), // <- nuevo
        ]);

        /* 🔥 Aplicar promo a todos los clientes (UNA SOLA VEZ) */
        if ($promocion->aplicar_a_todos) {
            Cliente::query()->update([
                'promo_activa' => true,
                'promo_desde'  => $request->fecha_inicio,
                'promo_hasta'  => $request->fecha_fin,
            ]);
        }

        return redirect()->route('admin.promociones.index')
            ->with('mensaje', 'Promoción creada correctamente')
            ->with('icono', 'success');
    }

    /* ============================
     * FORM EDITAR
     * ============================ */
    public function edit($id)
    {
        $promocion = Promocion::findOrFail($id);
        $productos = Producto::orderBy('nombre')->get();

        return view('admin.promociones.edit', compact('promocion', 'productos'));
    }

    /* ============================
     * ACTUALIZAR
     * ============================ */
    public function update(Request $request, $id)
    {
        $promocion = Promocion::findOrFail($id);

        $request->validate([
            'nombre'       => 'required|string|max:255',
            'producto_id'  => 'required|integer|exists:productos,id',
            'precio_promo' => 'required|numeric|min:0',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'activa'       => 'nullable|boolean',
            'aplicar_a_todos'       => 'nullable|boolean',
        ]);

        $promocion->update([
            'nombre'       => $request->nombre,
            'producto_id'  => $request->producto_id,
            'precio_promo' => $request->precio_promo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin'    => $request->fecha_fin,
            'activa'       => $request->boolean('activa'),
            'aplicar_a_todos'  => $request->has('aplicar_a_todos'), // <- nuevo
        ]);

        /* 🔥 LÓGICA CLAVE DEL CHECKBOX */
        if ($promocion->aplicar_a_todos) {

            // Activar promo para todos
            Cliente::query()->update([
                'promo_activa' => true,
                'promo_desde'  => $request->fecha_inicio,
                'promo_hasta'  => $request->fecha_fin,
            ]);

        } else {

            // Quitar promo a TODOS los clientes
            Cliente::query()->update([
                'promo_activa' => false,
                'promo_desde'  => null,
                'promo_hasta'  => null,
            ]);
        }

        return redirect()->route('admin.promociones.index')
            ->with('mensaje', 'Promoción actualizada correctamente')
            ->with('icono', 'success');
    }

    /* ============================
     * ACTIVAR / DESACTIVAR PROMO
     * ============================ */
    public function toggle($id)
    {
        $promocion = Promocion::findOrFail($id);
        $promocion->activa = !$promocion->activa;
        $promocion->save();


        return redirect()->back()
            ->with('mensaje', 'Estado de la promoción actualizado')
            ->with('icono', 'success');
    }

    /* ============================
     * ELIMINAR
     * ============================ */
    public function destroy($id)
    {
        Promocion::findOrFail($id)->delete();




        return redirect()->back()
            ->with('mensaje', 'Promoción eliminada')
            ->with('icono', 'success');
    }
}