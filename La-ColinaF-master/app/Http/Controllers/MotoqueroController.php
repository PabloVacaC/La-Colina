<?php

namespace App\Http\Controllers;

use App\Models\Motoquero;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MotoqueroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $motoqueros = Motoquero::all();
        return view('admin.motoqueros.index', compact('motoqueros'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::all();
        return view('admin.motoqueros.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'ci' => 'required|string|max:20|unique:motoqueros,ci',
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:M,F,Otro',
            'celular' => 'required|string|max:20',
            'email' => 'required|email|unique:users,email',
            'direccion' => 'required|string|max:255',
            'placa' => 'required|string|max:20',
            'rol' => 'required|exists:roles,name',
            'password' => 'required|min:8|confirmed'
        ]);

        $usuario = new User();
        $usuario->name = $request->nombres." ".$request->apellidos;
        $usuario->email = $request->email;
        $usuario->password = Hash::make($request->password);
        $usuario->save();

        $usuario->assignRole($request->rol);

        $motoquero = new Motoquero();
        $motoquero->usuario_id = $usuario->id;
        $motoquero->nombres = $request->nombres;
        $motoquero->apellidos = $request->apellidos;
        $motoquero->ci = $request->ci;
        $motoquero->fecha_nacimiento = $request->fecha_nacimiento;
        $motoquero->genero = $request->genero;
        $motoquero->celular = $request->celular;
        $motoquero->direccion = $request->direccion;
        $motoquero->placa = $request->placa;
        $motoquero->save();

        return redirect()->route('admin.motoqueros.index')
            ->with('mensaje', 'Motoquero registrado correctamente')
            ->with('icono', 'success');


    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $motoquero = Motoquero::findOrFail($id);
        return view('admin.motoqueros.show', compact('motoquero'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $roles = Role::all();
        $motoquero = Motoquero::find($id);
        return view('admin.motoqueros.edit', compact('motoquero','roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $motoquero = Motoquero::findOrFail($id);
        
        $request->validate([
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'ci' => 'required|string|max:20|unique:motoqueros,ci,'.$motoquero->id,
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:M,F,Otro',
            'celular' => 'required|string|max:20',
            'email' => 'required|email|unique:users,email,'.$motoquero->usuario_id,
            'direccion' => 'required|string|max:255',
            'placa' => 'nullable|string|max:20',
            'rol' => 'required|exists:roles,name',
            'password' => 'nullable|min:8|confirmed'
        ]);
    
        // Actualizar el usuario asociado
        $usuario = User::findOrFail($motoquero->usuario_id);
        $usuario->name = $request->nombres." ".$request->apellidos;
        $usuario->email = $request->email;
        
        // Solo actualizar contraseña si se proporcionó una nueva
        if($request->has('password') && $request->password) {
            $usuario->password = Hash::make($request->password);
        }
        
        $usuario->save();
    
        // Sincronizar roles (remover todos y asignar el nuevo)
        $usuario->syncRoles([$request->rol]);
    
        // Actualizar datos del motoquero
        $motoquero->nombres = $request->nombres;
        $motoquero->apellidos = $request->apellidos;
        $motoquero->ci = $request->ci;
        $motoquero->fecha_nacimiento = $request->fecha_nacimiento;
        $motoquero->genero = $request->genero;
        $motoquero->celular = $request->celular;
        $motoquero->direccion = $request->direccion;
        $motoquero->placa = $request->placa;
        $motoquero->save();
    
        return redirect()->route('admin.motoqueros.index')
            ->with('mensaje', 'Motoquero actualizado correctamente')
            ->with('icono', 'success');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $motoquero = Motoquero::find($id);
        $motoquero->delete();
        $motoquero->usuario->delete();

        return redirect()->route('admin.motoqueros.index')
            ->with('mensaje', 'Motoquero eliminado correctamente')
            ->with('icono', 'success');
    }
}
