<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::all();
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles',
        ]);

        $rol = new Role();
        $rol->name = $request->name;
        $rol->save();

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se registro el rol de la manera correcta')
            ->with('icono','success');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function permisos($id){
        $rol = Role::find($id);
        $permisos = Permission::all()->groupBy(function($permiso){

            if(stripos($permiso->name,'configuracion') !== false){ return 'Configuración';}
            if(stripos($permiso->name,'roles') !== false){ return 'Roles';}
            if(stripos($permiso->name,'pedidos') !== false){ return 'Pedidos';}
            if(stripos($permiso->name,'clientes') !== false){ return 'Clientes';}
            if(stripos($permiso->name,'productos') !== false){ return 'Productos';}
            if(stripos($permiso->name,'tarifas') !== false){ return 'Tarifas';}
            if(stripos($permiso->name,'motoqueros') !== false){ return 'Motoqueros';}
            if(stripos($permiso->name,'usuarios') !== false){ return 'Usuarios';}


        });


        return view('admin.roles.permisos',compact('permisos','rol'));
    }

    public function update_permisos(Request $request, $id){
        $rol = Role::find($id);
        $rol->permissions()->sync($request->input('permisos'));

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se modificaron los permisos de la manera correcta')
            ->with('icono','success');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $rol = Role::find($id);
        return view('admin.roles.edit',compact('rol'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,'.$id,
        ]);

        $rol = Role::find($id);
        $rol->name = $request->name;
        $rol->save();

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se modifico el rol de la manera correcta')
            ->with('icono','success');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        Role::destroy($id);

        return redirect()->route('admin.roles.index')
            ->with('mensaje','Se elimino el rol de la manera correcta')
            ->with('icono','success');
    }
}
