<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Cliente extends Authenticatable
{
    use HasFactory, Notifiable;

    public function adminlte_image(): string
    {
        return (string) ($this->avatar ?: 'https://picsum.photos/300/300');
    }

    public function adminlte_desc(): string
    {
        return (string) ($this->rol ?: 'tiktokero');
    }

    public function adminlte_profile_url(): string
    {
        return route('clientes.dashboard', absolute: false);
    }

    protected $fillable = [
        'provider',
        'google_id',
        'name',
        'email',
        'password',
        'rol',
        'avatar',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
