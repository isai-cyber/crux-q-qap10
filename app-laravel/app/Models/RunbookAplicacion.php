<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RunbookAplicacion extends Model
{
    public $timestamps = false;

    protected $table = 'runbook_aplicaciones';

    protected $fillable = ['runbook_id', 'ticket_id', 'resultado'];

    public function runbook()
    {
        return $this->belongsTo(Runbook::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
