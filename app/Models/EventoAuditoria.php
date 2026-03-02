<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoAuditoria extends Model
{
    use HasFactory;

    protected $table = 'eventos_auditoria';

    protected $fillable = [
        'codigo',
        'auditoria_id',
        'user_id',
    ];

    public function auditoria()
    {
        return $this->belongsTo(Auditoria::class, 'auditoria_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
