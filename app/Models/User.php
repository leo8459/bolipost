<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
<<<<<<< HEAD

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;
=======
use App\Models\Driver;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;
>>>>>>> a41ccfb (Uchazara)

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

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
<<<<<<< HEAD
=======

    /**
     * Compatibility attribute for imported Vitacora modules.
     * Maps BoliPost role names to the role labels expected by those modules.
     */
    public function getRoleAttribute(): string
    {
        $role = (string) ($this->getRoleNames()->first() ?? '');

        return match (mb_strtolower($role)) {
            'administrador' => 'admin',
            'cartero' => 'conductor',
            default => 'recepcion',
        };
    }

    /**
     * Resolve the related driver profile used by gestiones modules.
     */
    public function resolvedDriver(): ?Driver
    {
        $query = Driver::query()->where('user_id', $this->id);

        if (!empty($this->email)) {
            $query->orWhere('email', $this->email);
        }

        return $query->first();
    }
>>>>>>> a41ccfb (Uchazara)
}
