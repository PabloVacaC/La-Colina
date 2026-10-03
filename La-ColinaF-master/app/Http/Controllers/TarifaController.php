<?php

namespace App\Http\Controllers;

use App\Models\Tarifa;
use Illuminate\Http\Request;

class TarifaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tarifas = Tarifa::all();
        return view('admin.tarifas.index', compact('tarifas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.tarifas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'desde' => 'required|string|max:255',
            'hasta' => 'required|string|max:255',
            'distancia' => 'required|numeric|min:0',
            'precio' => 'required|numeric|min:0',
        ]);

        $tarifa = new Tarifa();
        $tarifa->desde = $request->desde;
        $tarifa->hasta = $request->hasta;
        $tarifa->distancia = $request->distancia;
        $tarifa->precio = $request->precio;
        $tarifa->save();

        return redirect()->route('admin.tarifas.index')
            ->with('mensaje', 'Tarifa registrada correctamente')
            ->with('icono', 'success');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tarifa = Tarifa::find($id);
        return view('admin.tarifas.show', compact('tarifa'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $tarifa = Tarifa::find($id);
        return view('admin.tarifas.edit', compact('tarifa'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'desde' => 'required|string|max:255',
            'hasta' => 'required|string|max:255',
            'distancia' => 'required|numeric|min:0',
            'precio' => 'required|numeric|min:0',
        ]);
        
        $tarifa = Tarifa::find($id);
        $tarifa->desde = $request->desde;
        $tarifa->hasta = $request->hasta;
        $tarifa->distancia = $request->distancia;
        $tarifa->precio = $request->precio;
        $tarifa->save();
        
        return redirect()->route('admin.tarifas.index')
            ->with('mensaje', 'Tarifa actualizada correctamente')
            ->with('icono', 'success');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tarifa = Tarifa::find($id);
        $tarifa->delete();
        return redirect()->route('admin.tarifas.index')
            ->with('mensaje', 'Tarifa eliminada correctamente')
            ->with('icono', 'success');
    }
}
