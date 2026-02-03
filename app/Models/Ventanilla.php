<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ventanilla extends Model
{
    use HasFactory;

    protected $table = 'ventanilla';

    protected $fillable = [
        'nombre_ventanilla',
    ];

    public function paquetesCerti()
    {
        return $this->hasMany(PaqueteCerti::class, 'fk_ventanilla');
    }
}
