<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroApi extends Model
{
    protected $table = 'registros_apis';

    protected $fillable = [
        'method',
        'url',
        'token',
        'description',
        'json_body',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'json_body' => 'encrypted',
    ];
}
