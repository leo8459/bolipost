<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    use HasFactory;

    protected $table = 'estados';

    protected $fillable = [
        'nombre_estado',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function paquetesCerti()
    {
        return $this->hasMany(PaqueteCerti::class, 'fk_estado');
    }
}
