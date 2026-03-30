<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopPartChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'codigo_pieza_nueva',
        'codigo_pieza_antigua',
        'descripcion',
        'costo',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
