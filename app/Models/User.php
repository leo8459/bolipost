<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'super admin' => 'admin',
        'superadmin' => 'admin',
        'super administrador' => 'admin',
        'superadministrador' => 'admin',
        'super admin nuevo' => 'admin',
        'recepcion' => 'recepcion',
        'recepcionista' => 'recepcion',
        'taller' => 'taller',
        'tallerista' => 'taller',
        'workshop' => 'taller',
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
        return 'profile';
    }
    protected $fillable = [
        'name',
        'alias',
        'email',
        'password',
        'ciudad',
        'regionales',
        'ci',
        'empresa_id',
        'sucursal_id',
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
        'regionales' => 'array',
        'auto_baja_empresa_at' => 'datetime',
    ];

    public function regionalesLista(): array
    {
        $regionales = is_array($this->regionales) ? $this->regionales : [];
        if ($regionales === [] && trim((string) $this->ciudad) !== '') {
            $regionales = [(string) $this->ciudad];
        }

        return collect($regionales)
            ->map(fn ($regional) => strtoupper(trim((string) $regional)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function regionalesTexto(): string
    {
        return implode(', ', $this->regionalesLista());
    }

    public function isSuperAdmin(): bool
    {
        $superAdminRole = trim((string) config('acl.super_admin_role', 'administrador'));

        return $superAdminRole !== ''
            && method_exists($this, 'hasRole')
            && $this->hasRole($superAdminRole);
    }

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

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

}

