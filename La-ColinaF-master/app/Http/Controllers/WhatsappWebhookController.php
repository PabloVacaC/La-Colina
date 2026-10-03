<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WhatsappMessage;

class WhatsappWebhookController extends Controller
{
    // Método para verificar el webhook (GET)
    public function verify(Request $request)
    {
        // 'mi_token_secreto' es el token que registraste en Meta Developers
        if ($request->hub_mode === 'subscribe' && $request->hub_verify_token === 'mi_token_secreto') {
            return $request->hub_challenge;
        }
        return response('Token no válido', 403);
    }

    // Método para recibir mensajes (POST)
public function receive(Request $request)
{
    $entry = $request->input('entry')[0] ?? [];
    $changes = $entry['changes'][0] ?? [];
    $value = $changes['value'] ?? [];

    // Verificamos que venga un mensaje
    if(!empty($value['messages'][0])) {
        $message = $value['messages'][0];

        // Tomamos el contacto correspondiente
        $contact = $value['contacts'][0] ?? [];

        WhatsappMessage::create([
            'from' => $message['from'] ?? 'Desconocido',
            'name' => $contact['profile']['name'] ?? 'Desconocido',
            'message' => $message['text']['body'] ?? '',
        ]);

        \Log::info('Mensaje guardado: ' . $message['text']['body']);
    }

    return response('OK', 200);
}
}
