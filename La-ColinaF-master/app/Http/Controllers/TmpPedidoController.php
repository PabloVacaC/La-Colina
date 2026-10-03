<?php

namespace App\Http\Controllers;

use App\Models\TmpPedido;
use Illuminate\Http\Request;

class TmpPedidoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'producto' => 'required|string|max:255',
            'detalle' => 'nullable|string|max:255',
            'cantidad' => 'required|integer|min:1',
            'precio_unitario' => 'required|numeric|min:0',
            'precio_total' => 'required|numeric|min:0',
        ]);

        $session_id = session()->getId();

        $tmpPedido = new TmpPedido();
        $tmpPedido->session_id = $session_id;
        $tmpPedido->producto = $request->producto;
        $tmpPedido->detalle = $request->detalle;
        $tmpPedido->cantidad = $request->cantidad;
        $tmpPedido->precio_unitario = $request->precio_unitario;
        $tmpPedido->precio_total = $request->precio_total;
        $tmpPedido->save();

        return redirect()->back()
                ->with('mensaje', 'Producto agregado al carrito')
                ->with('icono', 'success');
    }

    /**
     * Display the specified resource.
     */
    public function show(TmpPedido $tmpPedido)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TmpPedido $tmpPedido)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'producto' => 'required|string|max:255',
            'detalle' => 'nullable|string|max:255',
            'cantidad' => 'required|integer|min:1',
            'precio_unitario' => 'required|numeric|min:0',
            'precio_total' => 'required|numeric|min:0',
        ]);

        $tmpPedido = TmpPedido::find($id);
        $tmpPedido->producto = $request->producto;
        $tmpPedido->detalle = $request->detalle;
        $tmpPedido->cantidad = $request->cantidad;
        $tmpPedido->precio_unitario = $request->precio_unitario;
        $tmpPedido->precio_total = $request->precio_total;
        $tmpPedido->save(); 

        return redirect()->back()
                ->with('mensaje', 'Producto actualizado en el carrito')
                ->with('icono', 'success');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tmpPedido = TmpPedido::find($id);
        $tmpPedido->delete();

        return redirect()->back()
                ->with('mensaje', 'Producto eliminado del carrito')
                ->with('icono', 'success');
    }
}
