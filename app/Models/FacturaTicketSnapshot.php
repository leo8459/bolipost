<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacturaTicketSnapshot extends Model
{
    protected $fillable = ['cart_id', 'data', 'captured_at'];

    protected $casts = ['data' => 'array', 'captured_at' => 'datetime'];
}
