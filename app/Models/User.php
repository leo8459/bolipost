<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * Alias de rol usado por varios modulos legacy de gestiones.
     */
    private const ROLE_ALIASES = [
        'administrador' => 'admin',
        'admin' => 'admin',
        'recepcion' => 'recepcion',
        'recepcionista' => 'recepcion',
        'conductor' => 'conductor',
    ];

    public function adminlte_image()
    {
        return 'https://picsum.photos/300/300';
    }
    public function adminlte_desc()
    {
        // Recupera el nombre del rol del usuario actual
        return $this->getRoleNames()->implode(', ');
    }
    
    public function adminlte_profile_url()
    {
        return 'profile/username';
    }
    protected $fillable = [
        'name',
        'alias',
        'email',
        'password',
        'ciudad',
        'ci',
        'empresa_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class, 'user_id');
    }

    public function getRoleAttribute(): ?string
    {
        $roleName = (string) ($this->getRoleNames()->first() ?? '');
        if ($roleName === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($roleName));

        return self::ROLE_ALIASES[$normalized] ?? $normalized;
    }

    public function resolvedDriver(): ?Driver
    {
        $driver = $this->driver()->first();
        if ($driver) {
            return $driver;
        }

        $email = trim((string) $this->email);
        if ($email !== '') {
            $driver = Driver::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();

            if ($driver) {
                return $driver;
            }
        }

        return null;
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

