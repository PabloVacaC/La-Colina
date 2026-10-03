<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConfiguracionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtener JSON remoto
        $jsonData = file_get_contents('https://api.hilariweb.com/divisas');
        // Decodificar JSON a array asociativo
        $divisas = json_decode($jsonData, true);

        $configuracion = Configuracion::first();
        return view('admin.configuraciones.index',compact('configuracion','divisas'));
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
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:20',
            'correo_electronico' => 'required|email|max:255',
            'web' => 'nullable|url|max:255',
            'divisa' => 'required',
            'logo' => 'image|mimes:jpeg,png,jpg',
        ]);

        // Buscar si existe un registro
        $configuracion = Configuracion::first();

        if($configuracion){
            //Actualizar
            $configuracion->nombre = $request->nombre;
            $configuracion->descripcion = $request->descripcion;
            $configuracion->direccion = $request->direccion;
            $configuracion->telefono = $request->telefono;
            $configuracion->divisa = $request->divisa;
            $configuracion->web = $request->web;
            $configuracion->correo_electronico = $request->correo_electronico;

            if($request->hasFile('logo')){
                //Eliminar logo anterior
                if($configuracion->logo && file_exists(public_path($configuracion->logo))){
                    unlink(public_path($configuracion->logo));
                }
                $logoPath = $request->file('logo');
                $nombreArchivo = time() . '_' . $logoPath->getClientOriginalName();
                $rutaDestenio = public_path('uploads/logos');
                $logoPath->move($rutaDestenio, $nombreArchivo);
                $configuracion->logo = 'uploads/logos/' . $nombreArchivo;
            }

            $configuracion->save();

        } else {
            //Crear nueva configuracion
            $configuracion = new Configuracion();
            $configuracion->nombre = $request->nombre;
            $configuracion->descripcion = $request->descripcion;
            $configuracion->direccion = $request->direccion;
            $configuracion->telefono = $request->telefono;
            $configuracion->divisa = $request->divisa;
            $configuracion->web = $request->web;
            $configuracion->correo_electronico = $request->correo_electronico;

            if($request->hasFile('logo')){
                //Guardar nuevo logo
                $logoPath = $request->file('logo');
                $nombreArchivo = time() . '_' . $logoPath->getClientOriginalName();
                $rutaDestenio = public_path('uploads/logos');
                $logoPath->move($rutaDestenio, $nombreArchivo);
                $configuracion->logo = 'uploads/logos/' . $nombreArchivo;
            }

            $configuracion->save();

        }

        return redirect()->back()
            ->with('mensaje', 'Configuración guardada correctamente')
            ->with('icono', 'success');



    }

    /**
     * Display the specified resource.
     */
    public function show(Configuracion $configuracion)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Configuracion $configuracion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Configuracion $configuracion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Configuracion $configuracion)
    {
        //
    }
}
