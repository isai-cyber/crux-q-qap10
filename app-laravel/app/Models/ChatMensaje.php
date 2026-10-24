<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizacion;
use Illuminate\Database\Eloquent\Model;

class ChatMensaje extends Model
{
    use BelongsToOrganizacion;

    public $timestamps = false;

    protected $fillable = ['organizacion_id', 'usuario_id', 'rol', 'contenido', 'ticket_id'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
