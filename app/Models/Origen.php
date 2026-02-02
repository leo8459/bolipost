<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Origen extends Model
{
    use HasFactory;

    protected $table = 'origen';

    protected $fillable = [
        'nombre_origen',
    ];
}
