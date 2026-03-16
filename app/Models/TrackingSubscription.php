<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSubscription extends Model
{
    protected $fillable = [
        'codigo',
        'fcm_token',
        'package_name',
        'last_sig',
    ];
}
