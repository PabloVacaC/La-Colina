<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ==========================================
// RUTAS API PARA LA APP FLUTTER DE MOTOQUERO
// ==========================================
use App\Http\Controllers\Api\MotoqueroApiController;

// Autenticación
Route::post('/login', [MotoqueroApiController::class, 'login']);

// Pedidos del motoquero
Route::get('/motoquero/{id}/pedidos', [MotoqueroApiController::class, 'getPedidos']);
Route::post('/pedidos/{id}/tomar', [MotoqueroApiController::class, 'tomarPedido']);
Route::post('/pedidos/{id}/rechazar', [MotoqueroApiController::class, 'rechazarPedido']);
Route::post('/pedidos/{id}/finalizar', [MotoqueroApiController::class, 'finalizarPedido']);
Route::post('/motoquero/{id}/tomar-ruta', [MotoqueroApiController::class, 'tomarRuta']);

// Subir foto de casa o comprobante
Route::post('/clientes/{id}/imagen', [MotoqueroApiController::class, 'subirFotoCasa']);

// GPS Tracking en tiempo real
Route::post('/motoquero/ubicacion', [MotoqueroApiController::class, 'guardarUbicacion']);
Route::get('/motoqueros/ubicaciones', [MotoqueroApiController::class, 'getUbicacionesAdmin']);
Route::get('/productos', [MotoqueroApiController::class, 'getProductos']);

