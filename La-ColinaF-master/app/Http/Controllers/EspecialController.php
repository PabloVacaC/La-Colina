<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\PrecioEspecial;
use Illuminate\Http\Request;

class EspecialController extends Controller
{
    public function index()
    {
        $clientes = Cliente::whereHas('preciosEspeciales')->withCount('preciosEspeciales')->get();
        return view('admin.especiales.index', compact('clientes'));
    }

    public function create()
    {
        $clientes = Cliente::all();
        $productos = Producto::orderBy('nombre')->get();
        return view('admin.especiales.create', compact('clientes','productos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'precios' => 'nullable|array',
            'precios.*.precio' => 'nullable|numeric|min:0',
            'precios.*.producto_id' => 'nullable|integer|exists:productos,id',
        ]);

        $clienteId = $request->cliente_id;
        $precios = $request->input('precios', []);

        // Borrar anteriores
        PrecioEspecial::where('cliente_id', $clienteId)->delete();

        foreach ($precios as $item) {
            // si no tiene precio, ignorar
            if (!isset($item['precio']) || $item['precio'] === '' || $item['precio'] === null) {
                continue;
            }

            PrecioEspecial::create([
                'cliente_id' => $clienteId,
                'producto_id' => $item['producto_id'],
                'precio_especial' => $item['precio'],
            ]);
        }

        return redirect()->route('admin.especiales.index')
            ->with('mensaje', 'Precios especiales guardados correctamente')
            ->with('icono', 'success');
    }

    public function edit($cliente_id)
    {
        $cliente = Cliente::findOrFail($cliente_id);
        $productos = Producto::orderBy('nombre')->get();

        $precios = PrecioEspecial::where('cliente_id', $cliente_id)
            ->pluck('precio_especial','producto_id')->toArray();

        return view('admin.especiales.edit', compact('cliente','productos','precios'));
    }

    public function update(Request $request, $cliente_id)
    {
        $request->validate([
            'precios' => 'nullable|array',
            'precios.*.precio' => 'nullable|numeric|min:0',
            'precios.*.producto_id' => 'nullable|integer|exists:productos,id',
        ]);

        PrecioEspecial::where('cliente_id', $cliente_id)->delete();

        $precios = $request->input('precios', []);
        foreach ($precios as $item) {
            if (!isset($item['precio']) || $item['precio'] === '' || $item['precio'] === null) {
                continue;
            }

            PrecioEspecial::create([
                'cliente_id' => $cliente_id,
                'producto_id' => $item['producto_id'],
                'precio_especial' => $item['precio'],
            ]);
        }

        return redirect()->route('admin.especiales.index')
            ->with('mensaje', 'Precios especiales actualizados')
            ->with('icono', 'success');
    }

    public function destroy($cliente_id)
    {
        PrecioEspecial::where('cliente_id', $cliente_id)->delete();

        return redirect()->route('admin.especiales.index')
            ->with('mensaje', 'Precios especiales eliminados')
            ->with('icono', 'success');
    }
}