<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Motoquero;
use App\Models\Tarifa;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Producto;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $total_roles = Role::count();
    $total_motoqueros = Motoquero::count();
    $total_tarifas = Tarifa::count();
    $total_clientes = Cliente::count();
    $total_pedidos = Pedido::count();
    $total_usuarios = User::count();
    $total_productos = Producto::count();

    if (Auth::user()->roles->pluck('name')->implode(', ') == 'MOTOQUERO') {
        $total_pedidos_asignados = Pedido::where('motoquero_id', Auth::user()->motoquero->id)
            ->where('estado', 'Pendiente')->count();
    } else {
        $total_pedidos_asignados = Pedido::where('estado', 'Pendiente')->count();
    }

    // Obtener clientes por mes con nombres literales
    $clientesPorMes = Cliente::select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as count'))
        ->groupBy('month')
        ->get();

    // Array de meses en español
    $mesesLiterales = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    // Mapear los números de mes a nombres literales
    $meses = $clientesPorMes->pluck('month')->map(function ($month) use ($mesesLiterales) {
        return $mesesLiterales[$month];
    });
    $clientes = $clientesPorMes->pluck('count');

    // Obtener pedidos por mes con nombres literales
    $pedidosPorMes = Pedido::select(DB::raw('MONTH(created_at) as month'), DB::raw('COUNT(*) as count'))
        ->groupBy('month')
        ->get();

    // Mapear los números de mes a nombres literales
    $meses_pedidos = $pedidosPorMes->pluck('month')->map(function ($month) use ($mesesLiterales) {
        return $mesesLiterales[$month];
    });
    $pedidos = $pedidosPorMes->pluck('count');

    $total_pedidos_en_camino = Pedido::where('estado', 'En camino')->count();
    $total_pedidos_entregados = Pedido::where('estado', 'Entregado')->count();
    $total_pedidos_pendientes = Pedido::where('estado', 'Pendiente')->count();

    return view('admin.index', compact(
        'total_roles',
        'total_motoqueros',
        'total_tarifas',
        'total_clientes',
        'total_pedidos_asignados',
        'total_pedidos',
        'total_productos',
        'meses',
        'clientes',
        'pedidos',
        'meses_pedidos',
        'pedidos',
        'total_pedidos_en_camino',
        'total_pedidos_entregados',
        'total_pedidos_pendientes',
        'total_usuarios'
    ));
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
