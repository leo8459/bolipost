<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }
}
