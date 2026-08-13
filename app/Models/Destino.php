<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Destino extends Model
{
    use HasFactory;

    protected $table = 'destino';

    protected $fillable = [
        'nombre_destino',
    ];

    public function getNombrePreregistroAttribute(): string
    {
        return match (strtoupper(trim((string) $this->nombre_destino))) {
            'BENI' => 'TRINIDAD',
            'PANDO' => 'COBIJA',
            'SUCRE' => 'CHUQUISACA',
            default => strtoupper(trim((string) $this->nombre_destino)),
        };
    }

    public function getNombreEmsAttribute(): string
    {
        return match (strtoupper(trim((string) $this->nombre_destino))) {
            'PANDO' => 'COBIJA',
            'BENI' => 'TRINIDAD',
            'CHUQUISACA' => 'SUCRE',
            default => strtoupper(trim((string) $this->nombre_destino)),
        };
    }
}
