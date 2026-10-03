<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudLlamadaEvent implements ShouldBroadcast
{
    public $cliente_id;
    public $nombre;
    public $celular;
    public $motoquero;

    public function __construct($cliente_id, $nombre, $celular, $motoquero)
    {
        $this->cliente_id = $cliente_id;
        $this->nombre = $nombre;
        $this->celular = $celular;
        $this->motoquero = $motoquero;
    }

    public function broadcastOn()
    {
        return new Channel('admin-channel');
    }
}