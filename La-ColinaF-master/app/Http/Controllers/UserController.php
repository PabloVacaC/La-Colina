<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\Motoquero;
class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('admin.usuarios.index', compact('users'));
    }
    public function create()
    {
        $roles = Role::all();
        return view('admin.usuarios.create', compact('roles'));
    }
    public function store(Request $request)
    {
        //$datos = request()->all();
        //return response()->json($datos);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        $user->assignRole($request->role);


        return redirect()->route('admin.usuarios.index')
            ->with('mensaje', 'Usuario registrado correctamente')
            ->with('icono', 'success');

    }

    public function show($id)
    {
        $user = User::find($id);
        return view('admin.usuarios.show', compact('user'));
    }

    public function edit($id)
    {
        $usuario = User::find($id);
        $roles = Role::all();
        return view('admin.usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(Request $request, $id)   
    {
        $user = User::find($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        if($request->password){
            $user->password = Hash::make($request->password);
        }
        $user->save();

        $user->syncRoles($request->role);



        return redirect()->route('admin.usuarios.index')
            ->with('mensaje', 'Usuario actualizado correctamente')
            ->with('icono', 'success');
    }    

    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();
        return redirect()->route('admin.usuarios.index')
            ->with('mensaje', 'Usuario eliminado correctamente')
            ->with('icono', 'success');
    }
}
