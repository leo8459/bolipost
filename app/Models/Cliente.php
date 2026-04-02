<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class Cliente extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected string $guard_name = 'cliente';

    protected static function booted(): void
    {
        static::creating(function (self $cliente): void {
            if (blank($cliente->codigo_cliente)) {
                $cliente->codigo_cliente = static::nextCodigoCliente();
            }
        });
    }

    public function adminlte_image(): string
    {
        return (string) ($this->avatar ?: 'https://picsum.photos/300/300');
    }

    public function adminlte_desc(): string
    {
        $roleNames = method_exists($this, 'getRoleNames') ? $this->getRoleNames()->implode(', ') : '';

        return $roleNames !== '' ? $roleNames : (string) ($this->rol ?: 'tiktokero');
    }

    public function adminlte_profile_url(): string
    {
        return route('clientes.dashboard', absolute: false);
    }

    protected $fillable = [
        'codigo_cliente',
        'tipodocumentoidentidad',
        'complemento',
        'numero_carnet',
        'razon_social',
        'telefono',
        'direccion',
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

    public static function nextCodigoCliente(): string
    {
        return DB::transaction(function (): string {
            $ultimoCodigo = static::query()
                ->lockForUpdate()
                ->where('codigo_cliente', 'like', 'COD%')
                ->whereRaw('LENGTH(codigo_cliente) = 9')
                ->orderByDesc('codigo_cliente')
                ->value('codigo_cliente');

            $ultimoNumero = (int) preg_replace('/\D/', '', (string) $ultimoCodigo);
            $siguienteNumero = $ultimoNumero + 1;

            if ($siguienteNumero > 999999) {
                throw new \RuntimeException('Se alcanzo el limite maximo de codigos de cliente.');
            }

            return 'COD' . str_pad((string) $siguienteNumero, 6, '0', STR_PAD_LEFT);
        });
    }

    public function perfilCompleto(): bool
    {
        foreach (['tipodocumentoidentidad', 'numero_carnet', 'razon_social', 'telefono', 'direccion'] as $campo) {
            if (blank($this->{$campo})) {
                return false;
            }
        }

        return true;
    }

    public static function tiposDocumentoIdentidad(): array
    {
        return [
            '1' => 'CI - Cedula de identidad',
            '2' => 'CEX - Cedula de identidad de extranjero',
            '3' => 'PAS - Pasaporte',
            '4' => 'OD - Otro Documento de Identidad',
            '5' => 'NIT - Numero de identificacion Tributaria',
        ];
    }

    public function tipoDocumentoIdentidadLabel(): string
    {
        return static::tiposDocumentoIdentidad()[(string) $this->tipodocumentoidentidad]
            ?? (string) $this->tipodocumentoidentidad;
    }

    protected function getDefaultGuardName(): string
    {
        return 'cliente';
    }
}
